<?php

namespace App\Catalogue;

use App\Models\CatalogueCollection;
use App\Models\CatalogueMedia;
use App\Models\CatalogueProduct;
use App\Models\CatalogueVariant;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;

class CatalogueData
{
    public static function ok(array $data, int $status = 200)
    {
        return response()->json(['data' => $data, 'meta' => ApiResponse::meta()], $status);
    }

    public static function variants(int $product, bool $public = false): array
    {
        $q = CatalogueVariant::where('product_id', $product)->whereNull('archived_at');
        if ($public) {
            $q->where('active', true);
        }

        $variants = $q->orderBy('id')->get();
        $values = DB::table('catalogue_option_values')->whereIn('id', $variants->pluck('colour_value_id')->merge($variants->pluck('size_value_id')))->pluck('value', 'id');
        $stock = DB::table('stock_balances')->join('stock_locations', 'stock_locations.id', '=', 'stock_balances.location_id')->whereIn('variant_id', $variants->pluck('id'))->where('stock_locations.active', true)->select('stock_balances.*')->get()->groupBy('variant_id');

        return $variants->map(function ($v) use ($public, $values, $stock) {
            $colour = $values[$v->colour_value_id];
            $size = $values[$v->size_value_id];
            $balances = $stock->get($v->id, collect());
            $data = ['id' => $v->id, 'sku' => $v->sku, 'colour' => $colour, 'size' => $size, 'price_minor' => $v->price_minor, 'compare_at_minor' => $v->compare_at_minor, 'currency' => $v->currency, 'measurements' => $v->measurements, 'fit' => $v->fit, 'care' => $v->care, 'available' => $balances->sum(fn ($b) => $b->on_hand - $b->reserved), 'active' => $v->active];
            $data += ['stock_tracked' => Availability::tracked(), 'is_in_stock' => Availability::inStock((int) $data['available'], $v->active)];
            if (! $public) {
                $data += ['barcode' => $v->barcode, 'weight_grams' => $v->weight_grams, 'low_stock_threshold' => $v->low_stock_threshold];
            }

            return $data;
        })->all();
    }

    public static function media(CatalogueMedia $m, bool $public = false): array
    {
        $base = url('/api/v1/'.($public ? 'catalogue' : 'staff/catalogue').'/media/'.$m->id);

        return ['id' => $m->id, 'variant_id' => $m->variant_id, 'alt' => $m->alt, 'position' => $m->position, 'width' => $m->width, 'height' => $m->height, 'url' => $base.'/1280', 'sources' => collect($m->sizes)->unique('width')->map(fn ($s) => ['url' => $base.'/'.$s['size'], 'width' => $s['width'], 'height' => $s['height']])->values()->all()];
    }

    public static function product(CatalogueProduct $p, bool $public = false): array
    {
        $variants = self::variants($p->id, $public);
        $ids = array_column($variants, 'id');
        $media = CatalogueMedia::where('product_id', $p->id)->whereNull('archived_at')->orderBy('position')->orderBy('id')->get()->filter(fn ($m) => ! $public || ! $m->variant_id || in_array($m->variant_id, $ids))->map(fn ($m) => self::media($m, $public))->values()->all();
        $collections = CatalogueCollection::join('catalogue_collection_product', 'catalogue_collections.id', '=', 'collection_id')->where('product_id', $p->id)->when($public, fn ($q) => $q->where('active', true)->where(fn ($g) => $g->where('kind', 'collection')->orWhereIn('parent_id', CatalogueCollection::where('kind', 'collection')->where('active', true)->select('id'))))->orderBy('catalogue_collections.position')->get(['catalogue_collections.id', 'title', 'slug', 'kind', 'parent_id', 'active']);
        $data = ['id' => $p->id, 'title' => $p->title, 'slug' => $p->slug, 'product_type' => $p->product_type, 'description' => $p->description, 'material' => $p->material, 'fit' => $p->fit, 'care' => $p->care, 'measurements' => $p->measurements ?? [], 'seo_title' => $p->seo_title, 'seo_description' => $p->seo_description, 'collections' => $collections, 'variants' => $variants, 'media' => $media];
        if (! $public) {
            $data += ['version' => $p->version, 'status' => $p->status, 'published_at' => $p->published_at, 'updated_at' => $p->updated_at];
        }

        return $data;
    }
}
