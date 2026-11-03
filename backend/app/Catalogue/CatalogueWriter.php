<?php

namespace App\Catalogue;

use App\Models\CatalogueMedia;
use App\Models\CatalogueProduct;
use App\Models\CatalogueVariant;
use App\Security\StaffAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogueWriter
{
    public static function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    public static function locked(int $id, Request $r, bool $editable = true): CatalogueProduct
    {
        $p = CatalogueProduct::lockForUpdate()->findOrFail($id);
        $r->validate(['version' => 'required|integer|min:1']);
        if ($p->version !== (int) $r->input('version')) {
            abort(409, 'Product changed; reload before editing.');
        }
        if ($editable && $p->status !== 'draft') {
            self::fail('status', 'Unpublish this product before editing its saved content.');
        }

        return $p;
    }

    public static function save(Request $r, ?int $id = null): CatalogueProduct
    {
        return DB::transaction(function () use ($r, $id) {
            $p = $id ? self::locked($id, $r) : new CatalogueProduct;
            $data = $r->validate([
                'title' => 'required|string|max:160', 'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('catalogue_slugs', 'slug')->ignore($id, 'product_id')],
                'product_type' => 'nullable|string|max:120', 'description' => 'nullable|string|max:12000', 'material' => 'nullable|string|max:4000', 'fit' => 'nullable|string|max:4000', 'care' => 'nullable|string|max:4000', 'seo_title' => 'nullable|string|max:70', 'seo_description' => 'nullable|string|max:160',
                'measurements' => 'present|array|max:30', 'measurements.*' => 'required|array:size,chest_cm,length_cm,shoulder_cm', 'measurements.*.size' => 'required|string|max:80|distinct:ignore_case', 'measurements.*.chest_cm' => 'required|numeric|min:1|max:400', 'measurements.*.length_cm' => 'required|numeric|min:1|max:400', 'measurements.*.shoulder_cm' => 'required|numeric|min:1|max:400',
                'collection_ids' => 'present|array|max:30', 'collection_ids.*' => ['required', 'integer', 'distinct', Rule::exists('catalogue_collections', 'id')->whereNull('deleted_at')], 'variants' => 'present|array|max:100',
                'variants.*' => 'required|array:id,sku,barcode,colour,size,price_minor,compare_at_minor,currency,weight_grams,low_stock_threshold,active,measurements,fit,care',
                'variants.*.id' => 'nullable|integer', 'variants.*.sku' => 'required|string|max:80|regex:/^[A-Z0-9][A-Z0-9._-]*$/|distinct:ignore_case', 'variants.*.barcode' => 'nullable|string|max:80|distinct',
                'variants.*.colour' => 'required|string|max:80', 'variants.*.size' => 'required|string|max:80', 'variants.*.price_minor' => 'required|integer|min:0|max:100000000',
                'variants.*.compare_at_minor' => 'nullable|integer|min:1|max:100000000', 'variants.*.currency' => 'required|in:GBP', 'variants.*.weight_grams' => 'nullable|integer|min:1|max:100000', 'variants.*.low_stock_threshold' => 'required|integer|min:0|max:1000000', 'variants.*.active' => 'required|boolean',
                'variants.*.measurements' => 'nullable|array:chest_cm,length_cm,shoulder_cm', 'variants.*.measurements.*' => 'required|numeric|min:1|max:400', 'variants.*.fit' => 'nullable|string|max:4000', 'variants.*.care' => 'nullable|string|max:4000',
            ]);
            $seen = [];
            $combos = [];
            foreach ($data['variants'] as $n => $v) {
                if (isset($v['id'])) {
                    if (in_array($v['id'], $seen)) {
                        self::fail("variants.$n.id", 'Duplicate variant ID.');
                    }if (! $id || ! CatalogueVariant::where('product_id', $id)->where('id', $v['id'])->exists()) {
                        self::fail("variants.$n.id", 'Variant does not belong to this product.');
                    }$seen[] = $v['id'];
                }
                foreach (['sku', 'barcode'] as $field) {
                    if (! empty($v[$field]) && CatalogueVariant::where($field, $v[$field])->when(isset($v['id']), fn ($q) => $q->where('id', '!=', $v['id']))->exists()) {
                        self::fail("variants.$n.$field", ucfirst($field).' is already reserved, including archived variants.');
                    }
                }
                $combo = strtolower(trim($v['colour'])).'|'.strtolower(trim($v['size']));
                if (in_array($combo, $combos)) {
                    self::fail("variants.$n.size", 'Colour and size combinations must be unique.');
                }$combos[] = $combo;
                if (isset($v['compare_at_minor']) && $v['compare_at_minor'] <= $v['price_minor']) {
                    self::fail("variants.$n.compare_at_minor", 'Compare-at price must exceed selling price.');
                }
            }
            $p->fill(array_intersect_key($data, array_flip(['title', 'slug', 'product_type', 'description', 'material', 'fit', 'care', 'measurements', 'seo_title', 'seo_description'])));
            if ($id) {
                $p->version++;
            }$p->save();
            DB::table('catalogue_slugs')->updateOrInsert(['slug' => $p->slug], ['product_id' => $p->id]);

            $old = DB::table('catalogue_collection_product')->where('product_id', $p->id)->pluck('position', 'collection_id');
            $all = array_unique(array_merge($old->keys()->all(), $data['collection_ids']));
            sort($all);
            DB::table('catalogue_collections')->whereIn('id', $all)->orderBy('id')->lockForUpdate()->get();
            foreach ($all as $collection) {
                $was = $old->has($collection);
                $is = in_array($collection, $data['collection_ids']);
                if ($was && ! $is) {
                    DB::table('catalogue_collection_product')->where(['product_id' => $p->id, 'collection_id' => $collection])->delete();
                } elseif (! $was && $is) {
                    $last = DB::table('catalogue_collection_product')->where('collection_id', $collection)->max('position');
                    DB::table('catalogue_collection_product')->insert(['product_id' => $p->id, 'collection_id' => $collection, 'position' => $last === null ? 0 : $last + 1]);
                }
                if ($was !== $is) {
                    DB::table('catalogue_collections')->where('id', $collection)->increment('version');
                }
            }
            CatalogueVariant::where('product_id', $p->id)->whereNotIn('id', $seen)->update(['active' => false, 'archived_at' => now()]);
            foreach ($data['variants'] as $n => $v) {
                $values = [];
                foreach (['colour', 'size'] as $name) {
                    $option = DB::table('catalogue_options')->where(['product_id' => $p->id, 'name' => $name])->value('id') ?? DB::table('catalogue_options')->insertGetId(['product_id' => $p->id, 'name' => $name]);
                    $value = trim($v[$name]);
                    $values[$name.'_value_id'] = DB::table('catalogue_option_values')->where('option_id', $option)->whereRaw('lower(value) = ?', [mb_strtolower($value)])->value('id') ?? DB::table('catalogue_option_values')->insertGetId(['option_id' => $option, 'value' => $value]);
                }
                $variant = isset($v['id']) ? CatalogueVariant::findOrFail($v['id']) : new CatalogueVariant;
                if (CatalogueVariant::where('product_id', $p->id)->where($values)->when(isset($v['id']), fn ($q) => $q->where('id', '!=', $v['id']))->exists()) {
                    self::fail("variants.$n.size", 'This combination already exists in archived records. Restore its original variant ID rather than creating a replacement.');
                }
                $variant->fill(array_intersect_key($v, array_flip(['sku', 'barcode', 'price_minor', 'compare_at_minor', 'currency', 'weight_grams', 'low_stock_threshold', 'active', 'measurements', 'fit', 'care'])) + $values + ['product_id' => $p->id, 'archived_at' => null]);
                $variant->save();
            }
            StaffAudit::record($id ? 'catalogue.product_saved' : 'catalogue.product_created', auth('staff')->id(), 'product:'.$p->id);

            return $p->fresh();
        }, 3);
    }

    public static function publish(CatalogueProduct $p): void
    {
        $errors = [];
        foreach (['title', 'product_type', 'description', 'material', 'fit', 'care', 'seo_title', 'seo_description'] as $f) {
            if (! trim((string) $p->$f)) {
                $errors[$f] = 'Required before publication.';
            }
        }
        if (! $p->measurements) {
            $errors['measurements'] = 'Add verified garment measurements before publication.';
        }
        if (! DB::table('catalogue_collection_product')->join('catalogue_collections', 'collection_id', '=', 'catalogue_collections.id')->whereNull('catalogue_collections.deleted_at')->where('product_id', $p->id)->where('active', true)->where(fn ($q) => $q->where('kind', 'collection')->orWhereIn('parent_id', DB::table('catalogue_collections as parent')->whereNull('parent.deleted_at')->where('parent.kind', 'collection')->where('parent.active', true)->select('parent.id')))->exists()) {
            $errors['collection_ids'] = 'Assign an active category belonging to an active collection.';
        }
        $variants = CatalogueVariant::where('product_id', $p->id)->whereNull('archived_at')->where('active', true)->get();
        if ($variants->isEmpty()) {
            $errors['variants'] = 'Add at least one active variant.';
        }
        $media = CatalogueMedia::where('product_id', $p->id)->whereNull('archived_at')->get();
        foreach ($variants as $v) {
            $size = DB::table('catalogue_option_values')->where('id', $v->size_value_id)->value('value');
            $row = collect($p->measurements ?? [])->first(fn ($m) => mb_strtolower($m['size']) === mb_strtolower($size)) ?? [];
            $measurements = array_merge($row, $v->measurements ?? []);
            foreach (['chest_cm', 'length_cm', 'shoulder_cm'] as $key) {
                if (empty($measurements[$key])) {
                    $errors['measurements'] = 'Provide verified chest, length and shoulder measurements for every active size, or complete variant overrides.';
                }
            }
            if ($v->price_minor < 1) {
                $errors['variants'] = 'Every active variant needs a positive selling price.';
            }if (! $media->contains(fn ($m) => ! $m->variant_id || $m->variant_id === $v->id)) {
                $errors['media'] = 'Every active variant needs a shared or linked product image.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $p->status = 'published';
        $p->published_at = now();
        $p->first_published_at ??= now();
        $p->version++;
        $p->save();
        StaffAudit::record('catalogue.product_published', auth('staff')->id(), 'product:'.$p->id);
    }
}
