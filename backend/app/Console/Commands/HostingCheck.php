<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HostingCheck extends Command
{
    protected $signature = 'hosting:check';

    protected $description = 'PHP/image/database readiness report without exposing credentials';

    public function handle(): int
    {
        $gd = extension_loaded('gd') ? gd_info() : [];
        $checks = [
            'PHP >= 8.4.1' => PHP_VERSION_ID >= 80401,
            'PDO MySQL' => extension_loaded('pdo_mysql'),
            'GD JPEG/PNG/WebP' => ($gd['JPEG Support'] ?? false) && ($gd['PNG Support'] ?? false) && ($gd['WebP Support'] ?? false),
            'GD image driver selected' => config('catalogue.image_driver') === 'gd',
            'Private storage writable' => $this->storageWritable(storage_path('app/private')) && $this->storageWritable(storage_path('framework')),
            'Database queue configured' => config('queue.default') === 'database',
        ];
        try {
            $checks['MySQL/MariaDB connected'] = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) && (bool) DB::selectOne('SELECT 1 AS healthy');
            $checks['34 immutable evidence triggers'] = $checks['MySQL/MariaDB connected'] && DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', DB::getDatabaseName())->where('ACTION_TIMING', 'BEFORE')->where('ACTION_STATEMENT', 'like', '%SIGNAL%')->count() >= 34;
        } catch (\Throwable) {
            $checks['MySQL/MariaDB connected'] = false;
            $checks['34 immutable evidence triggers'] = false;
        }
        $this->table(['Check', 'Result'], array_map(fn ($label, $ok) => [$label, $ok ? 'PASS' : 'PENDING / FAIL'], array_keys($checks), array_values($checks)));
        $this->line('proc_open: '.(function_exists('proc_open') ? 'available' : 'disabled; GD/tick do not require it'));
        $this->line('Job timeout signals: '.(extension_loaded('pcntl') ? 'available' : 'unavailable; max-time is cooperative, verify host execution limits'));
        $this->warn('This report does not enable checkout or prove production readiness.');

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }

    private function storageWritable(string $directory): bool
    {
        if (! is_dir($directory)) {
            return false;
        }
        // Windows directory attributes can make is_writable misleading. Probe the actual operation.
        $path = $directory.DIRECTORY_SEPARATOR.'.hosting-check-'.bin2hex(random_bytes(16));
        $file = @fopen($path, 'x');
        if (! $file) {
            return false;
        }
        try {
            return fwrite($file, 'ok') === 2;
        } finally {
            fclose($file);
            @unlink($path);
        }
    }
}
