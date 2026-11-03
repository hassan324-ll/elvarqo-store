<?php

namespace App\Catalogue;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CategoryImage
{
    public static function encode(UploadedFile $file): string
    {
        $dir = storage_path('framework/cache/category-image-'.Str::uuid());
        File::ensureDirectoryExists($dir);
        try {
            $file->move($dir, 'original');
            try {
                app(ImageProcessor::class)->process($dir.'/original', $dir, true);
            } catch (\RuntimeException $e) {
                CatalogueWriter::fail('image', ImageProcessor::message($e->getMessage(), true));
            }

            // Keep only the generated 640px thumbnail. No original or temporary file survives.
            return base64_encode(File::get($dir.'/640.webp'));
        } finally {
            File::deleteDirectory($dir);
        }
    }
}
