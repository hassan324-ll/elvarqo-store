<?php

namespace Tests\MySQL;

use App\Models\StaffUser;
use App\Support\SqliteTransfer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SqliteTransferTest extends TestCase
{
    use DatabaseMigrations;

    private string $source;

    protected function setUp(): void
    {
        parent::setUp();
        config(['commerce.wise.enabled' => false]);
        $this->source = sys_get_temp_dir().DIRECTORY_SEPARATOR.'elvarqo-transfer-'.Str::uuid().'.sqlite';
        touch($this->source);
        $env = ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->source, 'DB_URL' => ''];
        (new Process([PHP_BINARY, 'artisan', 'migrate', '--force'], base_path(), $env))->mustRun();
        (new Process([PHP_BINARY, 'tests/Support/transfer-fixture.php'], base_path(), $env))->mustRun();
    }

    protected function tearDown(): void
    {
        if (isset($this->source)) {
            @unlink($this->source);
        }
        parent::tearDown();
    }

    public function test_preflight_and_copy_preserve_identity_images_receipts_and_reject_reimport(): void
    {
        $transfer = app(SqliteTransfer::class);
        $before = hash_file('sha256', $this->source);
        $counts = $transfer->run($this->source, 'mysql');
        $this->assertSame(2, $counts['catalogue_collections']);
        $this->assertDatabaseCount('staff_users', 0);
        $this->assertSame($counts, $transfer->run($this->source, 'mysql', true));
        $this->assertSame($before, hash_file('sha256', $this->source));
        $source = new PDO('sqlite:'.$this->source);
        $this->assertSame($source->query('SELECT password FROM staff_users')->fetchColumn(), StaffUser::first()->getRawOriginal('password'));
        $this->assertSame('JBSWY3DPEHPK3PXP', StaffUser::first()->two_factor_secret);
        $this->assertSame(base64_encode('Synthetic image bytes'), DB::table('catalogue_collections')->where('kind', 'category')->value('image_webp'));
        $this->assertSame('TRANSFER-QA-001', Crypt::decryptString(DB::table('wise_bank_transfers')->value('receipt_reference')));
        $this->assertDatabaseHas('commerce_orders', ['payment_state' => 'paid']);
        $this->assertDatabaseCount('customer_orders', 1);
        $this->assertDatabaseCount('staff_sessions', 0);
        $this->assertSame(19, (int) DB::table('stock_balances')->sum('on_hand'));
        $next = DB::table('catalogue_collections')->insertGetId(['title' => 'After transfer', 'slug' => 'after-transfer']);
        $this->assertGreaterThan(2, $next);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Destination contains records');
        $transfer->run($this->source, 'mysql', true);
    }

    public function test_invalid_json_rolls_back_every_business_row_and_restores_fk_checks(): void
    {
        $source = new PDO('sqlite:'.$this->source);
        $source->exec("UPDATE catalogue_products SET measurements='invalid-json'");
        try {
            app(SqliteTransfer::class)->run($this->source, 'mysql', true);
            $this->fail('Invalid JSON imported');
        } catch (QueryException) {
            $this->assertDatabaseCount('staff_users', 0);
            $this->assertDatabaseCount('catalogue_collections', 0);
            $this->assertDatabaseCount('staff_roles', 4);
            $this->assertSame(1, (int) DB::selectOne('SELECT @@FOREIGN_KEY_CHECKS AS enabled')->enabled);
        }
    }

    public function test_destination_json_shape_rewrite_is_detected_and_rolled_back(): void
    {
        DB::unprepared("CREATE TRIGGER qa_rewrite BEFORE INSERT ON catalogue_products FOR EACH ROW SET NEW.measurements = '{}'");
        try {
            app(SqliteTransfer::class)->run($this->source, 'mysql', true);
            $this->fail('Destination changed a JSON array into an object without detection');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Record verification failed', $e->getMessage());
            $this->assertDatabaseCount('catalogue_collections', 0);
            $this->assertDatabaseCount('catalogue_products', 0);
            $this->assertDatabaseCount('staff_roles', 4);
        }
    }

    public function test_wrong_encryption_key_is_rejected_before_writes(): void
    {
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        app()->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_KEY');
        app(SqliteTransfer::class)->run($this->source, 'mysql', true);
    }

    public function test_missing_host_trigger_privileges_cannot_silently_weaken_import(): void
    {
        DB::unprepared('DROP TRIGGER commerce_order_items_no_update');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('trigger missing');
        app(SqliteTransfer::class)->run($this->source, 'mysql', true);
    }

    public function test_imported_immutable_evidence_cannot_be_updated_or_deleted(): void
    {
        app(SqliteTransfer::class)->run($this->source, 'mysql', true);
        foreach (['staff_audit_events', 'commerce_order_snapshots', 'commerce_order_items', 'commerce_order_addresses', 'commerce_stock_events', 'customer_orders'] as $table) {
            foreach (['update', 'delete'] as $operation) {
                try {
                    $query = DB::table($table);
                    $operation === 'delete' ? $query->delete() : $query->update(['created_at' => now()]);
                    $this->fail('Immutable evidence changed: '.$table);
                } catch (QueryException $e) {
                    $this->assertStringContainsString('45000', $e->getMessage());
                }
            }
        }
        $this->assertSame(34, DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', DB::getDatabaseName())->count());
    }
}
