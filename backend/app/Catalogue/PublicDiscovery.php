<?php

namespace App\Catalogue;

use App\Models\CatalogueCollection as Collection;
use App\Models\CatalogueProduct as Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicDiscovery
{
    public static function eligible()
    {
        return DB::table('catalogue_variants as v')->whereColumn('v.product_id', 'catalogue_products.id')->where('v.active', true)->whereNull('v.archived_at');
    }

    public static function published()
    {
        return Product::where('status', 'published')->whereExists(self::eligible()->selectRaw('1'));
    }

    public static function collectionVisible($q)
    {
        return $q->whereExists(function ($q) {
            $q->selectRaw('1')->from('catalogue_collection_product as cp')->join('catalogue_products as p', 'p.id', '=', 'cp.product_id')->where(function ($g) {
                $g->whereColumn('cp.collection_id', 'catalogue_collections.id')->orWhereIn('cp.collection_id', DB::table('catalogue_collections as child')->whereColumn('child.parent_id', 'catalogue_collections.id')->whereNull('child.deleted_at')->where('child.kind', 'category')->where('child.active', true)->select('child.id'));
            })->where('p.status', 'published')->whereNull('p.deleted_at')->whereExists(function ($v) {
                $v->selectRaw('1')->from('catalogue_variants as v')->whereColumn('v.product_id', 'p.id')->where('v.active', true)->whereNull('v.archived_at');
            });
        });
    }

    public static function index(Request $r)
    {
        $v = $r->validate(['page' => 'nullable|integer|min:1|max:10000', 'per_page' => 'nullable|integer|min:1|max:50', 'q' => 'nullable|string|max:100', 'collection' => 'nullable|string|max:180', 'colour' => 'nullable|string|max:80', 'size' => 'nullable|string|max:80', 'min_price' => 'nullable|integer|min:0|max:100000000', 'max_price' => 'nullable|integer|min:0|max:100000000', 'stock' => 'nullable|in:all,in_stock', 'sort' => 'nullable|in:featured,newest,trending,price_asc,price_desc']);
        if (isset($v['min_price'],$v['max_price']) && $v['min_price'] > $v['max_price']) {
            CatalogueWriter::fail('max_price', 'Maximum price must be at least minimum price.');
        }
        $base = self::published();
        $collection = null;
        if (! empty($v['collection'])) {
            $collection = CategoryHierarchy::visible(Collection::where('slug', $v['collection']))->firstOrFail();
            $base->whereIn('catalogue_products.id', DB::table('catalogue_collection_product')->whereIn('collection_id', CategoryHierarchy::ids($collection->id, true))->select('product_id'));
        }
        if (! empty($v['q'])) {
            $needle = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower(trim($v['q']))).'%';
            $base->where(function ($q) use ($needle) {
                foreach (['title', 'description', 'product_type'] as $field) {
                    $q->orWhereRaw("LOWER(catalogue_products.$field) LIKE ? ESCAPE '!'", [$needle]);
                }$q->orWhereExists(self::eligible()->selectRaw('1')->whereRaw("LOWER(v.sku) LIKE ? ESCAPE '!'", [$needle]));
            });
        }
        // Facets use the query/collection scope before variant filters; hidden variants never supply options.
        $facetQuery = DB::table('catalogue_variants as v')->whereIn('v.product_id', (clone $base)->select('catalogue_products.id'))->where('v.active', true)->whereNull('v.archived_at');
        $colours = (clone $facetQuery)->join('catalogue_option_values as c', 'c.id', '=', 'v.colour_value_id')->distinct()->orderBy('c.value')->pluck('c.value');
        $sizes = (clone $facetQuery)->join('catalogue_option_values as s', 's.id', '=', 'v.size_value_id')->distinct()->orderBy('s.value')->pluck('s.value');
        $eligible = self::eligible();
        foreach (['colour', 'size'] as $name) {
            if (! empty($v[$name])) {
                $eligible->whereExists(fn ($q) => $q->selectRaw('1')->from('catalogue_option_values as value')->whereColumn('value.id', 'v.'.$name.'_value_id')->where(fn ($value) => $value->where('value.value', $v[$name])->orWhereRaw('LOWER(value.value) = ?', [mb_strtolower($v[$name])])));
            }
        }
        if (isset($v['min_price'])) {
            $eligible->where('v.price_minor', '>=', $v['min_price']);
        }if (isset($v['max_price'])) {
            $eligible->where('v.price_minor', '<=', $v['max_price']);
        }
        if (($v['stock'] ?? '') === 'in_stock') {
            $eligible->whereRaw('(SELECT COALESCE(SUM(b.on_hand-b.reserved),0) FROM stock_balances b JOIN stock_locations l ON l.id=b.location_id WHERE b.variant_id=v.id AND l.active=?) > 0', [true]);
        }
        $q = (clone $base)->whereExists((clone $eligible)->selectRaw('1'))->select('catalogue_products.*')->selectSub((clone $eligible)->selectRaw('MIN(v.price_minor)'), 'matching_price');
        $sort = $v['sort'] ?? 'featured';
        if ($sort === 'price_asc' || $sort === 'price_desc') {
            $q->orderBy('matching_price', $sort === 'price_asc' ? 'asc' : 'desc');
        } elseif ($sort === 'trending') {
            // Rank only confirmed GBP purchases from the past 30 days; never expose order data.
            $sales = DB::table('commerce_order_items as trend_items')->join('commerce_orders as trend_orders', 'trend_orders.id', '=', 'trend_items.order_id')
                ->whereColumn('trend_items.product_id', 'catalogue_products.id')
                ->where('trend_orders.order_state', 'confirmed')->where('trend_orders.payment_state', 'paid')
                ->where('trend_orders.currency', 'GBP')->where('trend_orders.paid_at', '>=', now()->subDays(30))
                ->selectRaw('COALESCE(SUM(trend_items.quantity), 0)');
            $q->selectSub($sales, 'trend_score')->orderByDesc('trend_score')->orderByDesc('first_published_at')->orderByDesc('catalogue_products.id');
        } elseif ($sort === 'newest') {
            $q->orderByDesc('first_published_at');
        } elseif ($collection) {
            $q->selectSub(DB::table('catalogue_collection_product')->where('collection_id', $collection->id)->whereColumn('product_id', 'catalogue_products.id')->select('position'), 'sort_position')->orderBy('sort_position');
        }
        $page = $q->orderBy('catalogue_products.id')->paginate($v['per_page'] ?? 20);
        $items = $page->getCollection()->map(function ($p) use ($v) {
            $data = CatalogueData::product($p, true);

            $allowed = array_filter($data['variants'], function ($variant) use ($v) {
                foreach (['colour', 'size'] as $key) {
                    if (! empty($v[$key]) && mb_strtolower($variant[$key]) !== mb_strtolower($v[$key])) {
                        return false;
                    }
                }
                if (isset($v['min_price']) && $variant['price_minor'] < $v['min_price']) {
                    return false;
                }
                if (isset($v['max_price']) && $variant['price_minor'] > $v['max_price']) {
                    return false;
                }

                return ($v['stock'] ?? '') !== 'in_stock' || $variant['available'] > 0;
            });
            $data['matched_variant_ids'] = array_column($allowed, 'id');
            $data['listing_price_minor'] = (int) $p->matching_price;

            return $data;
        })->all();

        return CatalogueData::ok(['items' => $items, 'page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'facets' => ['colours' => $colours, 'sizes' => $sizes], 'filters' => $v])->header('Cache-Control', 'no-store');
    }

    public static function saved(Request $r)
    {
        $v = $r->validate(['ids' => 'required|string|max:850|regex:/^[1-9][0-9]*(?:,[1-9][0-9]*)*$/']);
        $ids = array_unique(explode(',', $v['ids']));
        if (count($ids) > 50 || collect($ids)->contains(fn ($id) => strlen($id) > 15)) {
            CatalogueWriter::fail('ids', 'Save at most 50 valid product identifiers.');
        }
        $found = self::published()->whereIn('catalogue_products.id', $ids)->get()->keyBy('id');
        $items = [];
        $unavailable = [];
        foreach ($ids as $id) {
            if (isset($found[$id])) {
                $items[] = CatalogueData::product($found[$id], true);
            } else {
                $unavailable[] = (int) $id;
            }
        }

        return CatalogueData::ok(['items' => $items, 'unavailable_ids' => $unavailable])->header('Cache-Control', 'no-store');
    }
}
