<?php

namespace App\Http\Controllers;

use App\Catalogue\CatalogueData;
use App\Catalogue\CategoryHierarchy;
use App\Catalogue\PublicDiscovery;
use App\Models\CatalogueCollection;
use Illuminate\Http\Request;

class CatalogueSitemapController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate(['page' => 'sometimes|integer|min:1|max:10000']);
        $products = PublicDiscovery::published()->orderBy('id')->paginate(500, ['slug', 'updated_at']);
        $groups = $products->currentPage() === 1
            ? PublicDiscovery::collectionVisible(CategoryHierarchy::visible(CatalogueCollection::query()))
                ->orderBy('id')->get(['slug'])->map(fn ($group) => ['slug' => $group->slug])->all()
            : [];

        // Deliberately expose only current public slugs and genuine product edit dates.
        // Collection membership has no aggregate edit timestamp; omit rather than invent lastmod.
        return CatalogueData::ok([
            'products' => $products->getCollection()->map(fn ($product) => [
                'slug' => $product->slug,
                'lastmod' => $product->updated_at?->toISOString(),
            ])->all(),
            'groups' => $groups,
            'page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'total' => $products->total(),
        ])->header('Cache-Control', 'no-store');
    }
}
