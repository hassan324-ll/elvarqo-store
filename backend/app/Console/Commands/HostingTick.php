<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class HostingTick extends Command
{
    protected $signature = 'hosting:tick';

    protected $description = 'Run maintenance and a bounded queue drain in-process under one shared-host lock';

    public function handle(): int
    {
        if (config('queue.default') !== 'database') {
            $this->error('hosting:tick requires QUEUE_CONNECTION=database.');

            return self::FAILURE;
        }
        $path = config('hosting.lock_path', storage_path('framework/hosting-tick.lock'));
        File::ensureDirectoryExists(dirname($path));
        $lock = fopen($path, 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) {
                fclose($lock);
            }
            $this->info('Another hosting tick is running; skipped.');

            return self::SUCCESS;
        }
        $previous = config('hosting.enqueue_notifications', true);
        try {
            $deadline = microtime(true) + 45;
            // Drain existing work before adding another batch of outbox copies.
            $queue = config('queue.connections.database.queue', 'default');
            config(['hosting.enqueue_notifications' => ! DB::connection(config('queue.connections.database.connection'))->table(config('queue.connections.database.table', 'jobs'))->where('queue', $queue)->exists()]);
            $commands = ['commerce:wise-maintenance', 'orders:maintenance', 'after-sales:maintenance', 'commerce:mail'];
            if (config('commerce.provider') === 'stripe_sandbox' && config('commerce.enabled')) {
                $commands[] = 'commerce:reconcile';
            }
            foreach ($commands as $command) {
                if (microtime(true) >= $deadline - 5) {
                    $this->warn('Maintenance time budget reached; remaining tasks wait for the next tick.');

                    return self::SUCCESS;
                }
                if ($this->callSilent($command) !== self::SUCCESS) {
                    $this->error('Maintenance failed: '.$command);

                    return self::FAILURE;
                }
            }
            $seconds = min(30, max(1, (int) ($deadline - microtime(true) - 5)));
            $status = $this->callSilent('queue:work', ['connection' => 'database', '--queue' => $queue, '--stop-when-empty' => true, '--max-jobs' => 20, '--max-time' => $seconds, '--timeout' => 20, '--tries' => 3, '--sleep' => 0, '--no-interaction' => true]);
            $this->info('Hosting tick finished. Notifications remain private local copies.');

            return $status;
        } finally {
            config(['hosting.enqueue_notifications' => $previous]);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
