<?php

namespace App\Catalogue;

use RuntimeException;

class GdImageProcessor
{
    public function process(string $input, string $output, bool $category = false): array
    {
        if (! extension_loaded('gd') || ! function_exists('imagewebp') || ! (imagetypes() & IMG_WEBP)) {
            throw new RuntimeException('WORKER_UNAVAILABLE');
        }
        if (! is_file($input) || filesize($input) > 8 * 1024 * 1024) {
            throw new RuntimeException('BYTES');
        }
        $bytes = file_get_contents($input);
        $extra = [];
        $info = @getimagesize($input, $extra);
        if (! $info) {
            throw new RuntimeException('DECODE_FAILED');
        }
        $format = match ($info[2]) {
            IMAGETYPE_JPEG => 'jpeg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp',
            default => throw new RuntimeException('FORMAT'),
        };
        [$width, $height] = $info;
        $minimum = $category ? 64 : 200;
        if ($width < $minimum || $height < $minimum) {
            throw new RuntimeException('DIMENSIONS:'.$width.'x'.$height);
        }
        if ($width * $height > 24000000) {
            throw new RuntimeException('PIXELS');
        }
        $this->container($bytes, $format);
        $limit = trim(ini_get('memory_limit'));
        $available = $limit === '-1' ? 384 * 1024 * 1024 : $this->memoryBytes($limit) - memory_get_usage(true);
        if ($width * $height * 12 + strlen($bytes) * 2 + 16 * 1024 * 1024 > $available) {
            throw new RuntimeException('MEMORY');
        }
        $image = null;
        set_error_handler(static function () {
            throw new RuntimeException('DECODE_FAILED');
        });
        try {
            $image = imagecreatefromstring($bytes);
            if (! $image) {
                throw new RuntimeException('DECODE_FAILED');
            }
        } finally {
            restore_error_handler();
        }
        try {
            if (! imageistruecolor($image)) {
                imagepalettetotruecolor($image);
            }
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $orientation = $format === 'jpeg' ? $this->orientation($extra['APP1'] ?? '') : 1;
            if (in_array($orientation, [2, 5, 7], true)) {
                imageflip($image, IMG_FLIP_HORIZONTAL);
            } elseif ($orientation === 4) {
                imageflip($image, IMG_FLIP_VERTICAL);
            }
            $angle = match ($orientation) {
                3 => 180, 5, 8 => 90, 6, 7 => -90, default => 0
            };
            if ($angle !== 0) {
                $rotated = imagerotate($image, $angle, 0);
                if (! $rotated) {
                    throw new RuntimeException('DECODE_FAILED');
                }
                $image = $rotated;
            }
            $width = imagesx($image);
            $height = imagesy($image);
            $sizes = [];
            foreach ($category ? [640] : [640, 1280, 1920] as $size) {
                $w = min($size, $width);
                $h = max(1, (int) round($height * $w / $width));
                $resized = imagecreatetruecolor($w, $h);
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
                if (! imagecopyresampled($resized, $image, 0, 0, 0, 0, $w, $h, $width, $height)) {
                    throw new RuntimeException('DECODE_FAILED');
                }
                $path = $output.DIRECTORY_SEPARATOR.$size.'.webp';
                if (! @imagewebp($resized, $path, 82) || ! is_file($path) || filesize($path) < 12) {
                    throw new RuntimeException('WRITE_FAILED');
                }
                $saved = @getimagesize($path);
                if (! $saved || $saved[0] !== $w || $saved[1] !== $h || $saved[2] !== IMAGETYPE_WEBP) {
                    throw new RuntimeException('WRITE_FAILED');
                }
                $sizes[] = ['size' => $size, 'width' => $w, 'height' => $h];
                unset($resized);
            }

            return compact('width', 'height', 'format', 'sizes');
        } finally {
            unset($image);
        }
    }

    private function memoryBytes(string $value): int
    {
        $number = (int) $value;

        return $number * match (strtolower(substr($value, -1))) {
            'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1
        };
    }

    private function container(string $bytes, string $format): void
    {
        $length = strlen($bytes);
        if ($format === 'jpeg') {
            if (! str_starts_with($bytes, "\xff\xd8") || ! str_ends_with($bytes, "\xff\xd9")) {
                throw new RuntimeException('DECODE_FAILED');
            }

            return;
        }
        if ($format === 'webp') {
            if (substr($bytes, 0, 4) !== 'RIFF' || substr($bytes, 8, 4) !== 'WEBP' || unpack('V', substr($bytes, 4, 4))[1] + 8 !== $length) {
                throw new RuntimeException('DECODE_FAILED');
            }
            $offset = 12;
            while ($offset + 8 <= $length) {
                $type = substr($bytes, $offset, 4);
                $size = unpack('V', substr($bytes, $offset + 4, 4))[1];
                if ($offset + 8 + $size + ($size % 2) > $length) {
                    throw new RuntimeException('DECODE_FAILED');
                }
                if (in_array($type, ['ANIM', 'ANMF'], true) || $type === 'VP8X' && $size > 0 && (ord($bytes[$offset + 8]) & 2)) {
                    throw new RuntimeException('ANIMATED');
                }
                $offset += 8 + $size + $size % 2;
            }
            if ($offset !== $length) {
                throw new RuntimeException('DECODE_FAILED');
            }

            return;
        }
        $offset = 8;
        while ($offset + 12 <= $length) {
            $size = unpack('N', substr($bytes, $offset, 4))[1];
            $type = substr($bytes, $offset + 4, 4);
            if ($offset + 12 + $size > $length || hash('crc32b', substr($bytes, $offset + 4, $size + 4)) !== bin2hex(substr($bytes, $offset + 8 + $size, 4))) {
                throw new RuntimeException('DECODE_FAILED');
            }
            if (in_array($type, ['acTL', 'fcTL', 'fdAT'], true)) {
                throw new RuntimeException('ANIMATED');
            }
            $offset += $size + 12;
            if ($type === 'IEND') {
                if ($offset !== $length) {
                    throw new RuntimeException('DECODE_FAILED');
                }

                return;
            }
        }
        throw new RuntimeException('DECODE_FAILED');
    }

    private function orientation(string $app1): int
    {
        if (! str_starts_with($app1, "Exif\0\0")) {
            return 1;
        }
        $tiff = substr($app1, 6);
        $little = substr($tiff, 0, 2) === 'II';
        if (! $little && substr($tiff, 0, 2) !== 'MM' || strlen($tiff) < 8) {
            return 1;
        }
        $short = fn ($offset) => $offset + 2 <= strlen($tiff) ? unpack($little ? 'v' : 'n', substr($tiff, $offset, 2))[1] : null;
        $long = fn ($offset) => $offset + 4 <= strlen($tiff) ? unpack($little ? 'V' : 'N', substr($tiff, $offset, 4))[1] : null;
        $offset = $long(4);
        if ($offset === null || $offset + 2 > strlen($tiff)) {
            return 1;
        }
        $entries = min($short($offset), 256);
        for ($i = 0; $i < $entries; $i++) {
            $at = $offset + 2 + $i * 12;
            if ($at + 12 > strlen($tiff)) {
                return 1;
            }
            if ($short($at) === 0x112 && $short($at + 2) === 3 && $long($at + 4) === 1) {
                $orientation = $short($at + 8);

                return $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
            }
        }

        return 1;
    }
}
