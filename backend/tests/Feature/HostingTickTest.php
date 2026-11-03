<?php

namespace Tests\Feature;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class HostingTickTest extends TestCase
{
    use RefreshDatabase;

    private string $lockPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lockPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'elvarqo-tick-'.Str::uuid().'.lock';
        config(['hosting.lock_path' => $this->lockPath, 'queue.default' => 'database', 'commerce.provider' => 'wise_bank_transfer']);
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        @unlink($this->lockPath);
        parent::tearDown();
    }

    public function test_tick_drains_jobs_in_process_and_repeated_tick_is_safe(): void
    {
        HostingTickReceipt::dispatch('first');
        $this->artisan('hosting:tick')->assertSuccessful();
        Storage::disk('local')->assertExists('hosting-test/first.txt');
        $this->assertDatabaseCount('jobs', 0);
        $this->artisan('hosting:tick')->assertSuccessful();
        $this->assertSame(['hosting-test/first.txt'], Storage::disk('local')->files('hosting-test'));
    }

    public function test_overlapping_tick_does_not_consume_work(): void
    {
        $lock = fopen($this->lockPath, 'c');
        flock($lock, LOCK_EX);
        HostingTickReceipt::dispatch('locked');
        try {
            $this->artisan('hosting:tick')->expectsOutput('Another hosting tick is running; skipped.')->assertSuccessful();
            $this->assertDatabaseCount('jobs', 1);
            Storage::disk('local')->assertMissing('hosting-test/locked.txt');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        $this->artisan('hosting:tick')->assertSuccessful();
        Storage::disk('local')->assertExists('hosting-test/locked.txt');
    }

    public function test_tick_stops_after_twenty_jobs_and_maintenance_does_not_duplicate_existing_backlog(): void
    {
        foreach (range(1, 25) as $id) {
            HostingTickReceipt::dispatch((string) $id);
        }
        // An undelivered account notification must not enqueue a duplicate batch while a backlog remains.
        DB::table('customer_notifications')->insert(['id' => (string) Str::uuid(), 'encrypted_payload' => 'not-dispatched-in-this-tick', 'created_at' => now()]);
        $this->artisan('hosting:tick')->assertSuccessful();
        $this->assertDatabaseCount('jobs', 5);
        $this->assertCount(20, Storage::disk('local')->files('hosting-test'));
        $this->assertDatabaseCount('customer_notifications', 1);
    }

    public function test_non_database_queue_configuration_is_rejected(): void
    {
        config(['queue.default' => 'sync']);
        $this->artisan('hosting:tick')->assertFailed();
    }
}

class HostingTickReceipt implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $id) {}

    public function handle(): void
    {
        Storage::disk('local')->put('hosting-test/'.$this->id.'.txt', 'private test receipt');
    }
}
