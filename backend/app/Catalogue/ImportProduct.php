<?php

namespace App\Catalogue;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportProduct
{
    public static function plan(array $raw, array $files): array
    {
        $fields = ['title', 'slug', 'product_type', 'description', 'material', 'fit', 'care', 'measurements', 'collection_ids', 'groups', 'variants', 'seo_title', 'seo_description', 'images', 'supplier'];
        $v = Validator::make(['product' => $raw], [
            'product' => 'required|array:'.implode(',', $fields),
            'product.title' => 'required|string|max:160',
            'product.slug' => 'nullable|string|max:180',
            'product.collection_ids' => 'sometimes|array|max:30',
            'product.collection_ids.*' => 'required|integer|distinct',
            'product.supplier' => 'required|array:name,product_id,url,cost_minor,currency,shipping_cost_minor,checked_at',
            'product.supplier.name' => 'required|in:AliExpress',
            'product.supplier.product_id' => ['required', 'string', 'regex:/^[0-9]{8,40}$/D'],
            'product.supplier.url' => 'required|string|max:300',
            'product.groups' => 'sometimes|array|max:30',
            'product.groups.*' => 'required|array:collection_slug,category_slug',
            'product.groups.*.collection_slug' => 'required|string|max:180',
            'product.groups.*.category_slug' => 'nullable|string|max:180',
            'product.variants' => 'sometimes|array|max:100',
            'product.variants.*' => 'required|array:sku,barcode,colour,size,price_minor,compare_at_minor,currency,weight_grams,low_stock_threshold,active,measurements,fit,care,supplier_variant_id,supplier_cost_minor,supplier_currency,supplier_shipping_cost_minor,supplier_checked_at',
            'product.variants.*.supplier_variant_id' => 'nullable|string|max:100|distinct',
            'product.images' => 'sometimes|array|max:30',
            'product.images.*' => 'required|array:file,alt',
            'product.images.*.file' => 'required|string|max:220|distinct',
            'product.images.*.alt' => 'required|string|max:240',
        ]);
        $v->validate();
        $supplier = $raw['supplier'];
        $expected = 'https://www.aliexpress.com/item/'.$supplier['product_id'].'.html';
        if ($supplier['url'] !== $expected) {
            CatalogueWriter::fail('supplier.url', 'Use the canonical https://www.aliexpress.com/item/PRODUCT_ID.html URL matching the supplied ID.');
        }
        self::costs($supplier, 'supplier');
        $ids = $raw['collection_ids'] ?? [];
        if (! is_array($ids) || count($ids) > 30) {
            CatalogueWriter::fail('collection_ids', 'Use an array of existing group IDs.');
        }
        foreach ($raw['groups'] ?? [] as $group) {
            $parent = DB::table('catalogue_collections')->where('slug', $group['collection_slug'])->where('kind', 'collection')->whereNull('parent_id')->whereNull('deleted_at')->first();
            if (! $parent) {
                CatalogueWriter::fail('groups', 'Collection not found: '.$group['collection_slug']);
            }
            $ids[] = $parent->id;
            if (! empty($group['category_slug'])) {
                $child = DB::table('catalogue_collections')->where('slug', $group['category_slug'])->where('kind', 'category')->where('parent_id', $parent->id)->whereNull('deleted_at')->first();
                if (! $child) {
                    CatalogueWriter::fail('groups', 'Category does not belong to the selected collection: '.$group['category_slug']);
                }
                $ids[] = $child->id;
            }
        }
        $catalogue = array_intersect_key($raw, array_flip(['title', 'slug', 'product_type', 'description', 'material', 'fit', 'care', 'measurements', 'seo_title', 'seo_description']));
        $catalogue['slug'] = $raw['slug'] ?? Str::slug($raw['title'] ?? '');
        $catalogue['measurements'] ??= [];
        $catalogue['collection_ids'] = array_values(array_unique($ids));
        $catalogue['variants'] = [];
        $supplierVariants = [];
        $warnings = ['Draft only. No stock or fulfilment is imported. Verify supplier facts, prices, measurements, image rights and publication requirements before publishing.'];
        foreach ($raw['variants'] ?? [] as $n => $variant) {
            $s = ['external_id' => $variant['supplier_variant_id'] ?? null, 'cost_minor' => $variant['supplier_cost_minor'] ?? null, 'currency' => $variant['supplier_currency'] ?? null, 'shipping_cost_minor' => $variant['supplier_shipping_cost_minor'] ?? null, 'checked_at' => $variant['supplier_checked_at'] ?? null];
            self::costs($s, "variants.$n.supplier");
            $local = array_diff_key($variant, array_flip(['supplier_variant_id', 'supplier_cost_minor', 'supplier_currency', 'supplier_shipping_cost_minor', 'supplier_checked_at']));
            $rules = ['sku' => 'nullable|string|max:80|regex:/^[A-Z0-9][A-Z0-9._-]*$/', 'barcode' => 'nullable|string|max:80', 'colour' => 'nullable|string|max:80', 'size' => 'nullable|string|max:80', 'price_minor' => 'nullable|integer|min:0|max:100000000', 'compare_at_minor' => 'nullable|integer|min:1|max:100000000', 'currency' => 'nullable|in:GBP', 'weight_grams' => 'nullable|integer|min:1|max:100000', 'low_stock_threshold' => 'nullable|integer|min:0|max:1000000', 'active' => 'nullable|boolean', 'fit' => 'nullable|string|max:4000', 'care' => 'nullable|string|max:4000', 'measurements' => 'nullable|array:chest_cm,length_cm,shoulder_cm', 'measurements.*' => 'required|numeric|min:1|max:400'];
            Validator::make($local, $rules)->validate();
            $complete = ! empty($local['sku']) && ! empty($local['colour']) && ! empty($local['size']) && isset($local['price_minor']);
            if ($complete) {
                $local['currency'] = 'GBP';
                $local['low_stock_threshold'] ??= 0;
                $local['active'] ??= false;
                $s['catalogue_index'] = count($catalogue['variants']);
                $s['pending_catalogue'] = null;
                $catalogue['variants'][] = $local;
            } else {
                $s['catalogue_index'] = null;
                $s['pending_catalogue'] = $local;
                $warnings[] = 'Variant '.($n + 1).' is incomplete; supplied values will be kept privately as pending supplier data. No local variant, price or options will be invented.';
            }
            $supplierVariants[] = $s;
        }
        if (! $catalogue['variants']) {
            $warnings[] = 'No complete local variants. Add verified sizes, colours, SKUs and GBP selling prices in the product editor before publication.';
        }
        CatalogueWriter::validate(new Request($catalogue));
        $matches = [];
        foreach ($raw['images'] ?? [] as $image) {
            if (! ImportArchive::safePath($image['file'])) {
                CatalogueWriter::fail('images', 'Image reference contains an unsafe path.');
            }
            $file = $files[$image['file']] ?? null;
            $matches[] = $image + ['matched' => $file !== null, 'width' => $file['width'] ?? null, 'height' => $file['height'] ?? null, 'sha256' => $file['sha256'] ?? null];
            if (! $file || $file['error']) {
                CatalogueWriter::fail('images.'.$image['file'], $file['error'] ?? 'No matching file in the image ZIP. Upload the ZIP or correct the JSON filename.');
            }
        }
        $groups = DB::table('catalogue_collections')->whereIn('id', $catalogue['collection_ids'])->get(['id', 'title', 'slug', 'kind', 'parent_id', 'active'])->all();

        return ['payload' => ['catalogue' => $catalogue, 'supplier' => $supplier, 'supplier_variants' => $supplierVariants, 'images' => $matches, 'group_snapshots' => $groups],
            'preview' => ['title' => $catalogue['title'], 'slug' => $catalogue['slug'], 'supplier' => $supplier, 'groups' => $groups, 'images' => $matches, 'warnings' => $warnings, 'variant_count' => count($catalogue['variants']), 'pending_variant_count' => count($supplierVariants) - count($catalogue['variants'])]];
    }

    private static function costs(array $values, string $field): void
    {
        $validator = Validator::make($values, [
            'cost_minor' => 'nullable|integer|min:0|max:100000000',
            'shipping_cost_minor' => 'nullable|integer|min:0|max:100000000',
            'currency' => ['nullable', 'string', 'regex:/^[A-Z]{3}$/D'],
            'checked_at' => 'nullable|date_format:Y-m-d|before_or_equal:today',
        ]);
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($messages, $key) => [$field.'.'.$key => $messages])->all());
        }
        if ((isset($values['cost_minor']) || isset($values['shipping_cost_minor'])) && empty($values['currency'])) {
            CatalogueWriter::fail($field.'.currency', 'Supply the currency for supplier costs, or leave unknown costs empty.');
        }
    }
}
