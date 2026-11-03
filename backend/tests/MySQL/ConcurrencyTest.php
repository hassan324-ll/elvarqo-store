<?php

namespace Tests\MySQL;

use App\Commerce\Bags;
use App\Commerce\Checkout;
use App\Commerce\Pricing;
use App\Models\StaffRole;
use App\Models\StaffUser;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\CommerceFixture as F;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['commerce.provider' => 'wise_bank_transfer', 'commerce.wise.enabled' => true, 'commerce.wise.account_holder' => 'ELVARQO TEST ONLY', 'commerce.wise.sort_code' => '123456', 'commerce.wise.account_number' => '12345678']);
    }

    private function input(int $variant): array
    {
        $token = app(Bags::class)->create()['token'];
        $r = Request::create('/');
        $r->headers->set('Authorization', 'Bearer '.$token);
        app(Bags::class)->change($r, ['variant_id' => $variant, 'quantity' => 1, 'revision' => 1]);
        $details = F::details();
        $quote = app(Pricing::class)->quote(app(Bags::class)->owned($r), $details);

        return ['action' => 'checkout', 'token' => $token, 'values' => $details + ['quote_hash' => $quote['quote_hash'], 'attempt_id' => (string) Str::uuid(), 'terms' => true]];
    }

    private function race(array $inputs): array
    {
        $base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'elvarqo-mysql-race-'.Str::uuid();
        $files = [];
        $processes = [];
        try {
            foreach ($inputs as $i => $input) {
                $path = $base.'-'.$i.'.json';
                file_put_contents($path, json_encode($input, JSON_THROW_ON_ERROR));
                $files[] = $path;
                $p = new Process([PHP_BINARY, 'tests/Support/mysql-race-worker.php', $path, $base.'.go'], base_path(), ['APP_KEY' => config('app.key')]);
                $p->setTimeout(30);
                $p->start();
                $processes[] = $p;
            }
            $limit = microtime(true) + 15;
            while (! is_file($files[0].'.ready') || ! is_file($files[1].'.ready')) {
                foreach ($processes as $p) {
                    if ($p->isTerminated()) {
                        $this->fail('Race worker failed before barrier: '.$p->getErrorOutput());
                    }
                }
                if (microtime(true) >= $limit) {
                    $this->fail('Workers did not reach race barrier.');
                }
                usleep(10000);
            }
            touch($base.'.go');
            $outcomes = [];
            foreach ($processes as $p) {
                $p->wait();
                $this->assertTrue($p->isSuccessful(), $p->getErrorOutput());
                $outcomes[] = json_decode($p->getOutput(), true, flags: JSON_THROW_ON_ERROR)['outcome'];
            }
            sort($outcomes);

            return $outcomes;
        } finally {
            foreach ($processes as $p) {
                if ($p->isRunning()) {
                    $p->stop();
                }
            }
            foreach ([...$files, ...array_map(fn ($file) => $file.'.ready', $files), $base.'.go'] as $file) {
                @unlink($file);
            }
        }
    }

    public function test_two_processes_cannot_reserve_the_same_last_unit(): void
    {
        $variant = F::seed(1);
        $this->assertSame(['ok', 'rejected'], $this->race([$this->input($variant), $this->input($variant)]));
        $this->assertDatabaseCount('commerce_orders', 1);
        $this->assertDatabaseCount('commerce_reservations', 1);
        $this->assertSame(1, (int) DB::table('stock_balances')->sum('reserved'));
        $this->assertSame(1, (int) DB::table('stock_balances')->sum('on_hand'));
    }

    public function test_two_processes_confirm_the_same_receipt_without_double_stock_commit(): void
    {
        $input = $this->input(F::seed(1));
        $r = Request::create('/');
        $r->headers->set('Authorization', 'Bearer '.$input['token']);
        $order = app(Checkout::class)->start($r, $input['values']);
        $actor = StaffUser::create(['name' => 'Concurrent QA', 'email' => 'race@example.test', 'password' => 'TestOnly-Password-42!', 'staff_role_id' => StaffRole::where('name', 'owner')->value('id')]);
        $input = ['action' => 'confirm', 'order' => $order['id'], 'actor' => $actor->id, 'values' => ['operation_id' => (string) Str::uuid(), 'confirmed' => true, 'amount_minor' => $order['total_minor'], 'currency' => 'GBP', 'payment_reference' => $order['reference'], 'receipt_reference' => 'CONCURRENT-RECEIPT-001']];
        $this->assertSame(['ok', 'ok'], $this->race([$input, $input]));
        $this->assertSame(0, (int) DB::table('stock_balances')->sum('on_hand'));
        $this->assertSame(0, (int) DB::table('stock_balances')->sum('reserved'));
        $this->assertDatabaseCount('commerce_stock_events', 2);
        $this->assertDatabaseCount('commerce_mail_outbox', 1);
        $this->assertDatabaseHas('commerce_orders', ['payment_state' => 'paid']);
    }
}
