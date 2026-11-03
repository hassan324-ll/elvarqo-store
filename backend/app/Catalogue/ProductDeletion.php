<?php

namespace App\Catalogue;

use App\Models\CatalogueProduct;
use App\Security\StaffAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ProductDeletion
{
    public static function delete(int $id, int $version, ?int $actor): array
    {
        $directories = DB::transaction(function () use ($id, $version, $actor) {
            $p = CatalogueProduct::withTrashed()->lockForUpdate()->findOrFail($id);
            abort_if($p->version !== $version, 409, 'Product changed. Reload before deleting.');
            if ($p->status === 'published') {
                CatalogueWriter::fail('product', 'Unpublish this product before permanently deleting it.');
            }
            $variants = DB::table('catalogue_variants')->where('product_id', $id)->orderBy('id')->lockForUpdate()->pluck('id');
            $balances = DB::table('stock_balances')->whereIn('variant_id', $variants)->orderBy('id')->lockForUpdate()->get();
            if (DB::table('commerce_order_items')->where('product_id', $id)->exists()
                || DB::table('commerce_reservations')->whereIn('variant_id', $variants)->exists()
                || DB::table('stock_movements')->whereIn('variant_id', $variants)->exists()
                || DB::table('after_sales_returns')->whereIn('exchange_variant_id', $variants)->exists()
                || $balances->contains(fn ($b) => $b->on_hand !== 0 || $b->reserved !== 0)) {
                CatalogueWriter::fail('product', 'This product has order, reservation, stock or return history and must be retained. Use Archive instead.');
            }
            $media = DB::table('catalogue_media')->where('product_id', $id)->pluck('id');
            $root = realpath(config('catalogue.media_root'));
            $directories = [];
            foreach ($media as $uuid) {
                if (! Str::isUuid($uuid)) {
                    CatalogueWriter::fail('media', 'Invalid image path; deletion stopped.');
                }
                $path = config('catalogue.media_root').DIRECTORY_SEPARATOR.$uuid;
                if (file_exists($path)) {
                    $resolved = realpath($path);
                    if (! $root || ! $resolved || dirname($resolved) !== $root || is_link($path)) {
                        CatalogueWriter::fail('media', 'Image directory is outside the configured storage root; deletion stopped.');
                    }
                    $directories[] = $resolved;
                }
            }
            DB::table('customer_saved_items')->where('product_id', $id)->delete();
            DB::table('catalogue_media')->where('product_id', $id)->delete();
            DB::table('stock_balances')->whereIn('variant_id', $variants)->delete();
            DB::table('catalogue_variants')->where('product_id', $id)->delete();
            $options = DB::table('catalogue_options')->where('product_id', $id)->pluck('id');
            DB::table('catalogue_option_values')->whereIn('option_id', $options)->delete();
            DB::table('catalogue_options')->where('product_id', $id)->delete();
            DB::table('catalogue_collection_product')->where('product_id', $id)->delete();
            DB::table('catalogue_slugs')->where('product_id', $id)->delete();
            $p->forceDelete();
            StaffAudit::record('catalogue.product_deleted', $actor, 'product:'.$id);

            return $directories;
        }, 3);
        $pending = false;
        foreach ($directories as $directory) {
            // Revalidate immediately before filesystem removal; do not follow links.
            $root = realpath(config('catalogue.media_root'));
            $resolved = realpath($directory);
            if ($resolved && (! $root || dirname($resolved) !== $root || is_link($directory) || ! File::deleteDirectory($resolved))) {
                $pending = true;
            }
        }

        return ['deleted' => true, 'id' => $id, 'image_cleanup_pending' => $pending];
    }
}
