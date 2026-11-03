<?php

namespace App\Http\Controllers;

use App\Catalogue\CatalogueData as D;
use App\Catalogue\CatalogueWriter as W;
use App\Catalogue\CategoryHierarchy;
use App\Catalogue\CategoryImage;
use App\Catalogue\ImageProcessor;
use App\Catalogue\ProductDeletion;
use App\Models\CatalogueCollection as Collection;
use App\Models\CatalogueMedia as Media;
use App\Models\CatalogueProduct as Product;
use App\Models\CatalogueVariant as Variant;
use App\Security\StaffAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CatalogueController extends Controller
{
    public function index(Request $r)
    {
        $v = $r->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|in:all,draft,published,archived', 'collection_id' => 'nullable|integer|exists:catalogue_collections,id', 'page' => 'nullable|integer|min:1|max:10000']);
        $q = Product::query();
        if (($v['status'] ?? '') === 'all') {
            $q->withTrashed();
        } elseif (($v['status'] ?? '') === 'archived') {
            $q->withTrashed()->where('status', 'archived');
        } elseif (! empty($v['status'])) {
            $q->where('status', $v['status']);
        }
        if (! empty($v['collection_id'])) {
            $q->whereIn('id', DB::table('catalogue_collection_product')->whereIn('collection_id', CategoryHierarchy::ids((int) $v['collection_id']))->select('product_id'));
        }
        if (! empty($v['q'])) {
            $q->where(function ($q) use ($v) {
                $q->where('title', 'like', '%'.$v['q'].'%')->orWhere('slug', 'like', '%'.$v['q'].'%')->orWhereIn('id', Variant::where('sku', 'like', '%'.$v['q'].'%')->select('product_id'));
            });
        }
        $page = $q->orderByDesc('updated_at')->orderByDesc('id')->paginate(10);

        return D::ok(['items' => $page->getCollection()->map(fn ($p) => D::product($p))->all(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]);
    }

    public function show(int $id)
    {
        return D::ok(['product' => D::product(Product::findOrFail($id))]);
    }

    public function create(Request $r)
    {
        return D::ok(['product' => D::product(W::save($r))], 201);
    }

    public function update(Request $r, int $id)
    {
        return D::ok(['product' => D::product(W::save($r, $id))]);
    }

    public function destroy(Request $r, int $id)
    {
        $v = $r->validate(['version' => 'required|integer|min:1', 'confirmation' => 'required|in:DELETE']);

        return D::ok(ProductDeletion::delete($id, (int) $v['version'], auth('staff')->id()));
    }

    public function transition(Request $r, int $id, string $action)
    {
        return DB::transaction(function () use ($r, $id, $action) {
            $p = W::locked($id, $r, false);
            if ($action === 'publish') {
                if ($p->status !== 'draft') {
                    W::fail('status', 'Only a saved draft can be published.');
                }W::publish($p);
            } elseif ($action === 'unpublish') {
                if ($p->status !== 'published') {
                    W::fail('status', 'Only published products can be unpublished.');
                }$p->status = 'draft';
                $p->published_at = null;
                $p->version++;
                $p->save();
                StaffAudit::record('catalogue.product_unpublished', auth('staff')->id(), 'product:'.$p->id);
            } else {
                $r->validate(['confirmation' => 'required|in:ARCHIVE']);
                $p->status = 'archived';
                $p->published_at = null;
                $p->version++;
                $p->save();
                $p->delete();
                StaffAudit::record('catalogue.product_archived', auth('staff')->id(), 'product:'.$p->id);

                return D::ok(['archived' => true]);
            }

            return D::ok(['product' => D::product($p->fresh())]);
        }, 3);
    }

    public function collections()
    {
        $groups = Collection::orderBy('position')->orderBy('id')->get();
        $members = DB::table('catalogue_collection_product')->get()->groupBy('collection_id');
        $items = $groups->map(function ($c) use ($groups, $members) {
            $ids = [$c->id];
            if ($c->kind === 'collection') {
                $ids = array_merge($ids, $groups->where('kind', 'category')->where('parent_id', $c->id)->pluck('id')->all());
            }
            $products = collect($ids)->flatMap(fn ($id) => $members->get($id, collect())->pluck('product_id'))->unique();

            return array_merge($c->toArray(), ['product_count' => $products->count()]);
        });

        return D::ok(['items' => $items, 'catalogue_total' => Product::withTrashed()->count()]);
    }

    public function saveCollection(Request $r, ?int $id = null)
    {
        return DB::transaction(function () use ($r, $id) {
            $c = $id ? Collection::findOrFail($id) : new Collection;
            $v = $r->validate(['title' => 'required|string|max:160', 'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('catalogue_collections', 'slug')->ignore($id)], 'kind' => 'required|in:collection,category', 'parent_id' => ['nullable', 'integer', Rule::exists('catalogue_collections', 'id')->whereNull('deleted_at')], 'description' => 'nullable|string|max:4000', 'active' => 'required|boolean', 'position' => 'required|integer|min:0|max:100000', 'version' => $id ? 'required|integer|min:1' : 'nullable', 'image' => 'nullable|file|max:8192|mimetypes:image/jpeg,image/png,image/webp', 'image_alt' => 'nullable|string|max:240', 'remove_image' => 'nullable|boolean']);
            // Lock the small group tree consistently before validating a parent/kind change.
            $groups = Collection::orderBy('id')->lockForUpdate()->get();
            if ($id) {
                $c = $groups->firstWhere('id', $id);
            }
            $parentId = $v['parent_id'] ?? null;
            if ($v['kind'] === 'category') {
                $parent = $groups->firstWhere('id', $parentId);
                if (! $parent || $parent->kind !== 'collection' || $parent->parent_id || (int) $parentId === $id) {
                    W::fail('parent_id', 'Choose a top-level collection for this category.');
                }
                if ($id && $groups->where('parent_id', $id)->isNotEmpty()) {
                    W::fail('kind', 'Move this collection’s categories before changing its kind.');
                }
            } elseif ($parentId) {
                W::fail('parent_id', 'A top-level collection cannot have a parent.');
            }
            $v['parent_id'] = $v['kind'] === 'category' ? (int) $parentId : null;
            if ($id && $c->version !== (int) $v['version']) {
                abort(409);
            }unset($v['version']);
            if ($r->hasFile('image') && ! empty($v['remove_image'])) {
                W::fail('image', 'Choose replacement or removal, not both.');
            }
            if ($r->hasFile('image')) {
                $c->image_webp = CategoryImage::encode($r->file('image'));
                $c->image_id = (string) Str::uuid();
                $c->image_alt = trim($v['image_alt'] ?? '') ?: $v['title'];
            } elseif (! empty($v['remove_image'])) {
                $c->image_webp = null;
                $c->image_id = null;
                $c->image_alt = null;
            } elseif ($c->image_id && array_key_exists('image_alt', $v)) {
                $c->image_alt = trim($v['image_alt'] ?? '') ?: $v['title'];
            }
            unset($v['image'], $v['remove_image'], $v['image_alt']);
            $c->fill($v);
            if ($id) {
                $c->version++;
            }$c->save();
            StaffAudit::record('catalogue.collection_saved', auth('staff')->id(), 'collection:'.$c->id);

            return D::ok(['collection' => $c->fresh()], $id ? 200 : 201);
        }, 3);
    }

    public function archiveCollection(Request $r, int $id)
    {
        $v = $r->validate(['version' => 'required|integer|min:1', 'confirmation' => 'required|in:ARCHIVE']);

        return DB::transaction(function () use ($id, $v) {
            $groups = Collection::orderBy('id')->lockForUpdate()->get();
            $c = $groups->firstWhere('id', $id);
            abort_unless($c, 404);
            abort_if($c->version !== (int) $v['version'], 409);
            if ($groups->where('parent_id', $id)->isNotEmpty()) {
                W::fail('categories', 'Move or delete this collection’s categories first. Their products will be preserved.');
            }
            $c->active = false;
            $c->version++;
            $c->save();
            $c->delete();
            StaffAudit::record('catalogue.group_archived', auth('staff')->id(), 'collection:'.$id);

            return D::ok(['archived' => true, 'id' => $id]);
        }, 3);
    }

    public function collectionOrder(Request $r, int $id)
    {
        return DB::transaction(function () use ($r, $id) {
            $c = Collection::lockForUpdate()->findOrFail($id);
            $v = $r->validate(['version' => 'required|integer|min:1', 'product_ids' => 'present|array|max:1000', 'product_ids.*' => 'required|integer|distinct']);
            if ($c->version !== (int) $v['version']) {
                abort(409);
            }
            $existing = DB::table('catalogue_collection_product')->where('collection_id', $id)->pluck('product_id')->all();
            $ids = $v['product_ids'];
            sort($existing);
            $sorted = $ids;
            sort($sorted);
            if ($existing !== $sorted) {
                W::fail('product_ids', 'Provide every assigned product ID exactly once.');
            }
            foreach ($ids as $position => $product) {
                DB::table('catalogue_collection_product')->where(['collection_id' => $id, 'product_id' => $product])->update(['position' => $position]);
            }$c->version++;
            $c->save();
            StaffAudit::record('catalogue.collection_reordered', auth('staff')->id(), 'collection:'.$id);

            return D::ok(['collection' => $c]);
        });
    }

    public function collectionProducts(int $id)
    {
        Collection::findOrFail($id);

        return D::ok(['items' => DB::table('catalogue_collection_product')->join('catalogue_products', 'product_id', '=', 'catalogue_products.id')->where('collection_id', $id)->orderBy('position')->get(['catalogue_products.id', 'title', 'slug', 'status', 'position'])]);
    }

    public function upload(Request $r, int $id)
    {
        $v = $r->validate(['version' => 'required|integer|min:1', 'alt' => 'required|string|max:240', 'variant_id' => 'nullable|integer', 'file' => 'required|file|max:8192|mimetypes:image/jpeg,image/png,image/webp']);
        $product = Product::findOrFail($id);
        if ($product->status !== 'draft') {
            W::fail('status', 'Unpublish before changing media.');
        }
        $uuid = (string) Str::uuid();
        $dir = config('catalogue.media_root').'/'.$uuid;
        File::ensureDirectoryExists($dir);
        try {
            $original = $dir.'/original';
            $r->file('file')->move($dir, 'original');
            try {
                $meta = app(ImageProcessor::class)->process($original, $dir);
            } catch (\RuntimeException $e) {
                W::fail('file', ImageProcessor::message($e->getMessage()));
            }

            return DB::transaction(function () use ($r, $id, $v, $uuid, $meta, $original) {
                $p = W::locked($id, $r);
                if (isset($v['variant_id']) && ! Variant::where('product_id', $id)->where('id', $v['variant_id'])->whereNull('archived_at')->exists()) {
                    W::fail('variant_id', 'Choose a variant belonging to this product.');
                }
                if (Media::where('product_id', $id)->whereNull('archived_at')->count() >= 30) {
                    W::fail('file', 'Maximum 30 active images per product.');
                }
                $media = Media::create(['id' => $uuid, 'product_id' => $id, 'variant_id' => $v['variant_id'] ?? null, 'alt' => $v['alt'], 'position' => (Media::where('product_id', $id)->max('position') ?? -1) + 1, 'width' => $meta['width'], 'height' => $meta['height'], 'original_format' => $meta['format'], 'sizes' => $meta['sizes'], 'sha256' => hash_file('sha256', $original)]);
                $p->version++;
                $p->save();
                StaffAudit::record('catalogue.media_uploaded', auth('staff')->id(), 'media:'.$uuid);

                return D::ok(['product' => D::product($p->fresh()), 'media' => D::media($media)], 201);
            }, 3);
        } catch (\Throwable $e) {
            File::deleteDirectory($dir);
            throw $e;
        }
    }

    public function mediaUpdate(Request $r, int $id)
    {
        return DB::transaction(function () use ($r, $id) {
            $p = W::locked($id, $r);
            $v = $r->validate(['items' => 'present|array|max:30', 'items.*' => 'required|array:id,alt,variant_id', 'items.*.id' => 'required|uuid|distinct', 'items.*.alt' => 'required|string|max:240', 'items.*.variant_id' => 'nullable|integer']);
            $existing = Media::where('product_id', $id)->whereNull('archived_at')->pluck('id')->sort()->values()->all();
            $incoming = array_column($v['items'], 'id');
            sort($incoming);
            if ($existing !== $incoming) {
                W::fail('items', 'Include every current image exactly once; use archive to remove an image.');
            }
            foreach ($v['items'] as $pos => $item) {
                if (isset($item['variant_id']) && ! Variant::where('product_id', $id)->where('id', $item['variant_id'])->whereNull('archived_at')->exists()) {
                    W::fail('items', 'An image variant must belong to this product.');
                }Media::where('id', $item['id'])->update(['alt' => $item['alt'], 'variant_id' => $item['variant_id'] ?? null, 'position' => $pos]);
            }
            $p->version++;
            $p->save();
            StaffAudit::record('catalogue.media_updated', auth('staff')->id(), 'product:'.$id);

            return D::ok(['product' => D::product($p->fresh())]);
        }, 3);
    }

    public function mediaArchive(Request $r, int $id, string $media)
    {
        return DB::transaction(function () use ($r, $id, $media) {
            $p = W::locked($id, $r);
            $r->validate(['confirmation' => 'required|in:ARCHIVE']);
            $m = Media::where('product_id', $id)->whereNull('archived_at')->findOrFail($media);
            $m->archived_at = now();
            $m->save();
            $p->version++;
            $p->save();
            StaffAudit::record('catalogue.media_archived', auth('staff')->id(), 'media:'.$media);

            return D::ok(['product' => D::product($p->fresh())]);
        }, 3);
    }

    public function categoryImage(int $id, string $image)
    {
        $group = Collection::where('image_id', $image)->findOrFail($id);
        abort_unless($group->image_webp, 404);

        return response(base64_decode($group->image_webp, true))->header('Content-Type', 'image/webp')->header('X-Content-Type-Options', 'nosniff')->header('Cache-Control', 'no-store, private');
    }

    public function image(string $media, int $size)
    {
        $m = Media::whereNull('archived_at')->findOrFail($media);
        Product::findOrFail($m->product_id);

        return self::file($m, $size);
    }

    public static function file(Media $m, int $size)
    {
        abort_unless(in_array($size, [640, 1280, 1920]), 404);
        $file = config('catalogue.media_root').'/'.$m->id.'/'.$size.'.webp';
        abort_unless(is_file($file), 404);

        return response()->file($file, ['Content-Type' => 'image/webp', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private']);
    }
}
