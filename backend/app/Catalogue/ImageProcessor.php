<?php

namespace App\Catalogue;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

class ImageProcessor
{
    public function process(string $input, string $output, bool $category = false): array
    {
        if (config('catalogue.image_driver') === 'gd') {
            return app(GdImageProcessor::class)->process($input, $output, $category);
        }
        if (config('catalogue.image_driver') !== 'sharp' || ! function_exists('proc_open')) {
            throw new RuntimeException('WORKER_UNAVAILABLE');
        }
        $args = [config('catalogue.node_binary'), base_path('media/process.cjs'), $input, $output];
        if ($category) {
            $args[] = 'category';
        }
        try {
            $process = new Process($args, base_path(), ImageWorkerEnvironment::values());
            $process->setTimeout(40);
            $process->run();
        } catch (\Throwable $e) {
            Log::warning('Image worker unavailable', ['type' => $e::class]);
            throw new RuntimeException('WORKER_UNAVAILABLE');
        }
        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput());
            Log::warning('Image worker failed', ['exit_code' => $process->getExitCode(), 'worker_error' => substr($error, 0, 3000)]);
            throw new RuntimeException($process->getExitCode() !== 1 || $error === 'WORKER_UNAVAILABLE' ? 'WORKER_UNAVAILABLE' : $error);
        }

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function message(string $error, bool $category = false): string
    {
        return match (true) {
            str_starts_with($error, 'DIMENSIONS:') => 'This image is '.substr($error, 11).'px. Choose a '.($category ? 'category image at least 64px on each side; 400px or larger is recommended.' : 'product image at least 200px on each side.'),
            $error === 'FORMAT' => 'Choose a JPEG, PNG or WebP image.',
            $error === 'ANIMATED' => 'Animated images are not supported. Choose a still JPEG, PNG or WebP.',
            $error === 'PIXELS' => 'This image exceeds 24 megapixels. Resize it and try again.',
            $error === 'MEMORY' => 'This image is too large for the server image memory budget. Resize it and try again.',
            $error === 'WORKER_UNAVAILABLE' => 'The image processor is unavailable. For shared hosting enable GD with WebP support and set MEDIA_IMAGE_DRIVER=gd; for Sharp check the image worker installation.',
            $error === 'WRITE_FAILED' => 'The processed image could not be saved. Check private storage permissions and available space.',
            default => 'This image could not be decoded. Export it as a new JPEG, PNG or WebP and try again (maximum 8MB / 24MP).',
        };
    }
}
