<?php

namespace App\Catalogue;

use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class ImportArchive
{
    public static function safePath(string $name): bool
    {
        return strlen($name) <= 220 && preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._\/-]*$/D', $name)
            && ! preg_match('~(?:^|/)(?:con|prn|aux|nul|com[0-9]|lpt[0-9])(?:\.|/|$)~i', $name)
            && ! preg_match('~\.(?:/|$)~', $name)
            && ! str_contains($name, '//') && ! preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $name);
    }

    /** Never use extractTo: validate every entry before streaming bounded bytes. */
    public static function unpack(string $path, string $directory): array
    {
        if (! class_exists(ZipArchive::class)) {
            CatalogueWriter::fail('images', 'Enable the PHP zip extension to import image ZIPs.');
        }
        if (filesize($path) > config('catalogue_import.max_zip_bytes')) {
            CatalogueWriter::fail('images', 'Image ZIP must not exceed 32 MiB.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            CatalogueWriter::fail('images', 'Choose a valid, unencrypted image ZIP.');
        }
        try {
            if ($zip->numFiles > config('catalogue_import.max_entries')) {
                CatalogueWriter::fail('images', 'ZIP contains too many entries (maximum 200).');
            }
            $entries = [];
            $seen = [];
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'];
                $folder = str_ends_with($name, '/');
                $clean = $folder ? substr($name, 0, -1) : $name;
                $key = strtolower($clean);
                $zip->getExternalAttributesIndex($i, $opsys, $attributes);
                $type = ($attributes >> 16) & 0170000;
                if (! self::safePath($clean) || isset($seen[$key]) || ! in_array($type, [0, 0040000, 0100000], true) || ($folder && $type === 0100000) || (! $folder && $type === 0040000) || ($stat['encryption_method'] ?? 0) !== 0) {
                    CatalogueWriter::fail('images', 'ZIP contains an unsafe path, duplicate name, encrypted entry or non-regular file.');
                }
                $seen[$key] = true;
                if ($folder) {
                    continue;
                }
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    CatalogueWriter::fail('images', $extension === 'avif'
                        ? 'AVIF is not accepted by the current pipeline. Convert copies to still WebP first, keep the originals, update JSON filenames and upload the WebP ZIP.'
                        : 'Image ZIP may contain only JPEG, PNG and WebP files.');
                }
                $total += $stat['size'];
                if ($stat['size'] < 1 || $stat['size'] > config('catalogue_import.max_image_bytes') || $total > config('catalogue_import.max_expanded_bytes') || config('catalogue_import.max_ratio') < $stat['size'] / max(1, $stat['comp_size'])) {
                    CatalogueWriter::fail('images', 'ZIP exceeds per-image, expansion or compression-ratio limits (8 MiB/image; 128 MiB expanded).');
                }
                $entries[] = $stat;
            }
            $fileNames = array_map(fn ($entry) => strtolower($entry['name']), $entries);
            foreach ($fileNames as $name) {
                $parent = dirname($name);
                while ($parent !== '.') {
                    if (in_array($parent, $fileNames, true)) {
                        CatalogueWriter::fail('images', 'ZIP file paths conflict with directory paths.');
                    }
                    $parent = dirname($parent);
                }
            }
            $files = [];
            foreach ($entries as $stat) {
                $name = $stat['name'];
                $destination = $directory.'/'.$name;
                File::ensureDirectoryExists(dirname($destination), 0700);
                $input = $zip->getStream($name);
                if (! is_resource($input)) {
                    CatalogueWriter::fail('images', 'ZIP entry could not be read.');
                }
                $output = fopen($destination, 'xb');
                if (! $output) {
                    fclose($input);
                    throw new RuntimeException('Private import storage is unavailable.');
                }
                try {
                    $copied = stream_copy_to_stream($input, $output, $stat['size'] + 1);
                } finally {
                    fclose($input);
                    fclose($output);
                }
                chmod($destination, 0600);
                if ($copied !== $stat['size'] || sprintf('%u', crc32(file_get_contents($destination))) !== sprintf('%u', $stat['crc'])) {
                    CatalogueWriter::fail('images', 'ZIP entry failed its size or checksum check.');
                }
                $info = @getimagesize($destination);
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($destination);
                $expected = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][strtolower(pathinfo($name, PATHINFO_EXTENSION))];
                $error = null;
                if (! $info || $mime !== $expected) {
                    $error = 'File content does not match a supported still image.';
                } elseif ($info[0] < 200 || $info[1] < 200 || $info[0] * $info[1] > 24000000) {
                    $error = 'Image must be at least 200px per side and at most 24 megapixels.';
                }
                $files[$name] = ['file' => $name, 'width' => $info[0] ?? null, 'height' => $info[1] ?? null, 'error' => $error, 'sha256' => hash_file('sha256', $destination)];
            }

            return $files;
        } finally {
            $zip->close();
        }
    }
}
