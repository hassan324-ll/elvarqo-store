<?php

namespace App\Catalogue;

use App\Catalogue\CatalogueWriter as W;
use App\Models\CatalogueMedia as Media;
use App\Models\CatalogueProduct as Product;
use App\Models\CatalogueVariant as Variant;
use App\Security\StaffAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MediaWriter
{
    public static function upload(Request $r, int $id): array
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

                return ['product' => $p->fresh(), 'media' => $media];
            }, 3);
        } catch (\Throwable $e) {
            File::deleteDirectory($dir);
            throw $e;
        }
    }
}
