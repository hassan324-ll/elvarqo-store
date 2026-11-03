<?php

namespace App\Http\Controllers;

use App\Catalogue\CatalogueData as D;
use App\Catalogue\CategoryHierarchy;
use App\Catalogue\PublicDiscovery;
use App\Models\CatalogueCollection as Collection;
use App\Models\CatalogueMedia as Media;
use App\Models\CatalogueProduct as Product;
use App\Models\CatalogueVariant as Variant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicCatalogueController extends Controller
{
    public function index(Request $r)
    {
        return PublicDiscovery::index($r);
    }

    public function saved(Request $r)
    {
        return PublicDiscovery::saved($r);
    }

    public function show(string $slug)
    {
        $record = DB::table('catalogue_slugs')->where('slug', $slug)->first();
        abort_unless($record, 404);
        $p = Product::where('status', 'published')->findOrFail($record->product_id);
        abort_unless(Variant::where('product_id', $p->id)->where('active', true)->whereNull('archived_at')->exists(), 404);
        if ($p->slug !== $slug) {
            return D::ok(['canonical_slug' => $p->slug, 'redirect_url' => url('/api/v1/catalogue/products/'.$p->slug)], 301)->header('Location', url('/api/v1/catalogue/products/'.$p->slug))->header('Cache-Control', 'no-store');
        }

        return D::ok(['product' => D::product($p, true)])->header('Cache-Control', 'no-store');
    }

    public function navigation()
    {
        $groups = CategoryHierarchy::visible(Collection::query())->orderBy('position')->orderBy('id')->get(['id', 'title', 'slug', 'kind', 'parent_id', 'description', 'image_id', 'image_alt']);
        $items = $groups->where('kind', 'collection')->values()->map(fn ($c) => array_merge($c->toArray(), array_merge($c->publicImage(), ['categories' => $groups->where('parent_id', $c->id)->values()->map(fn ($child) => array_merge($child->toArray(), $child->publicImage()))->all()])));

        return D::ok(['items' => $items])->header('Cache-Control', 'no-store');
    }

    public function collections()
    {
        return D::ok(['items' => PublicDiscovery::collectionVisible(Collection::where('active', true)->where('kind', 'collection')->whereNull('parent_id'))->orderBy('position')->orderBy('id')->get(['id', 'title', 'slug', 'kind', 'description'])])->header('Cache-Control', 'no-store');
    }

    public function categoryImage(int $id, string $image)
    {
        $group = CategoryHierarchy::visible(Collection::where('image_id', $image))->findOrFail($id);
        abort_unless($group->image_webp, 404);

        return response(base64_decode($group->image_webp, true))->header('Content-Type', 'image/webp')->header('X-Content-Type-Options', 'nosniff')->header('Cache-Control', 'no-store');
    }

    public function image(string $media, int $size)
    {
        $m = Media::whereNull('archived_at')->findOrFail($media);
        Product::where('status', 'published')->findOrFail($m->product_id);
        if ($m->variant_id) {
            abort_unless(Variant::where('id', $m->variant_id)->where('active', true)->whereNull('archived_at')->exists(), 404);
        }

        return CatalogueController::file($m, $size);
    }
}
