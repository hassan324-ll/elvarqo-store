<?php

namespace Tests\Feature;

use App\Catalogue\GdImageProcessor;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class GdImageTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('gd') || ! (imagetypes() & IMG_WEBP)) {
            $this->markTestSkipped('Run the GD acceptance profile with a GD/WebP-enabled PHP runtime.');
        }
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'elvarqo-gd-'.Str::uuid();
        File::ensureDirectoryExists($this->directory);
    }

    protected function tearDown(): void
    {
        if (isset($this->directory)) {
            File::deleteDirectory($this->directory);
        }
        parent::tearDown();
    }

    private function image(string $format = 'png', int $width = 800, int $height = 400): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledrectangle($image, 0, 0, (int) ($width / 2) - 1, $height - 1, imagecolorallocate($image, 240, 20, 20));
        imagefilledrectangle($image, (int) ($width / 2), 0, $width - 1, (int) ($height / 2) - 1, imagecolorallocate($image, 20, 20, 240));
        $path = $this->directory.'/input.'.$format;
        match ($format) {
            'png' => imagepng($image, $path), 'jpeg' => imagejpeg($image, $path), 'webp' => imagewebp($image, $path)
        };

        return $path;
    }

    public function test_jpeg_png_webp_produce_valid_non_enlarged_metadata_free_derivatives(): void
    {
        foreach (['jpeg', 'png', 'webp'] as $format) {
            $meta = app(GdImageProcessor::class)->process($this->image($format), $this->directory);
            $this->assertSame($format, $meta['format']);
            $this->assertSame(800, $meta['width']);
            foreach ([640 => [640, 320], 1280 => [800, 400], 1920 => [800, 400]] as $size => $dimensions) {
                $info = getimagesize($this->directory.'/'.$size.'.webp');
                $this->assertSame($dimensions, [$info[0], $info[1]]);
                $this->assertSame(IMAGETYPE_WEBP, $info[2]);
                $bytes = file_get_contents($this->directory.'/'.$size.'.webp');
                $this->assertStringNotContainsString('EXIF', $bytes);
            }
        }
    }

    public function test_transparency_is_retained_and_category_thumbnail_does_not_enlarge(): void
    {
        app(GdImageProcessor::class)->process($this->image(), $this->directory);
        $result = imagecreatefromwebp($this->directory.'/640.webp');
        $this->assertSame(127, imagecolorsforindex($result, imagecolorat($result, 600, 300))['alpha']);
        $meta = app(GdImageProcessor::class)->process($this->image('png', 120, 120), $this->directory, true);
        $this->assertSame([['size' => 640, 'width' => 120, 'height' => 120]], $meta['sizes']);
    }

    public function test_all_eight_exif_orientations_have_correct_dimensions_and_pixels(): void
    {
        $path = $this->image('jpeg');
        $original = file_get_contents($path);
        // Position of the red/blue top-left quadrant after each EXIF transform.
        $expected = [1 => 'red', 2 => 'blue', 3 => 'black', 4 => 'red', 5 => 'red', 6 => 'red', 7 => 'black', 8 => 'blue'];
        foreach (range(1, 8) as $orientation) {
            $exif = "Exif\0\0II".pack('vVv', 42, 8, 1).pack('vvVv', 0x112, 3, 1, $orientation)."\0\0".pack('V', 0);
            $segment = "\xff\xe1".pack('n', strlen($exif) + 2).$exif;
            file_put_contents($path, substr($original, 0, 2).$segment.substr($original, 2));
            $meta = app(GdImageProcessor::class)->process($path, $this->directory);
            $this->assertSame($orientation >= 5 ? [400, 800] : [800, 400], [$meta['width'], $meta['height']]);
            $result = imagecreatefromwebp($this->directory.'/640.webp');
            $pixel = imagecolorsforindex($result, imagecolorat($result, 30, 30));
            $color = $pixel['red'] > 150 ? 'red' : ($pixel['blue'] > 150 ? 'blue' : 'black');
            $this->assertSame($expected[$orientation], $color, 'Orientation '.$orientation);
        }
    }

    public function test_animated_and_truncated_containers_are_rejected(): void
    {
        $path = $this->image();
        $png = file_get_contents($path);
        $payload = 'acTL'.pack('NN', 2, 0);
        $animated = substr($png, 0, 33).pack('N', 8).$payload.hex2bin(hash('crc32b', $payload)).substr($png, 33);
        $webpPath = $this->image('webp');
        $webp = file_get_contents($webpPath);
        $chunk = 'ANIM'.pack('V', 6).str_repeat("\0", 6);
        $animatedWebp = substr($webp, 0, 4).pack('V', strlen($webp) - 8 + strlen($chunk)).substr($webp, 8).$chunk;
        foreach ([[$animated, 'ANIMATED'], [$animatedWebp, 'ANIMATED'], [substr($png, 0, -8), 'DECODE_FAILED'], [substr($webp, 0, -3), 'DECODE_FAILED']] as [$bytes, $code]) {
            file_put_contents($path, $bytes);
            try {
                app(GdImageProcessor::class)->process($path, $this->directory);
                $this->fail('Invalid image accepted');
            } catch (RuntimeException $e) {
                $this->assertSame($code, $e->getMessage());
            }
        }
    }

    public function test_product_minimum_and_memory_budget_fail_before_decode(): void
    {
        $path = $this->image('png', 120, 120);
        try {
            app(GdImageProcessor::class)->process($path, $this->directory);
            $this->fail('Small product image accepted');
        } catch (RuntimeException $e) {
            $this->assertSame('DIMENSIONS:120x120', $e->getMessage());
        }
        $path = $this->image('png', 2000, 2000);
        $before = ini_get('memory_limit');
        ini_set('memory_limit', ((int) ceil(memory_get_usage(true) / 1048576) + 24).'M');
        try {
            app(GdImageProcessor::class)->process($path, $this->directory);
            $this->fail('Memory budget ignored');
        } catch (RuntimeException $e) {
            $this->assertSame('MEMORY', $e->getMessage());
        } finally {
            ini_set('memory_limit', $before);
        }
    }
}
