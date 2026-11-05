<?php

namespace Tests\Feature;

use App\AfterSales\Refunds;
use App\Commerce\Bags;
use App\Commerce\WiseBankTransfer;
use App\Fulfilment\Shipments;
use App\Models\StaffRole;
use App\Models\StaffUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\CommerceFixture as F;
use Tests\TestCase;

class WiseCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private int $variant;

    private StaffUser $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config(['commerce.provider' => 'wise_bank_transfer', 'commerce.wise.enabled' => true, 'commerce.wise.account_holder' => 'ELVARQO TEST ONLY', 'commerce.wise.sort_code' => '12-34-56', 'commerce.wise.account_number' => '12345678', 'commerce.wise.window_hours' => 24]);
        Http::preventStrayRequests();
        $this->variant = F::seed(10);
        $this->token = app(Bags::class)->create()['token'];
        $this->owner = StaffUser::create(['name' => 'Wise QA Owner', 'email' => 'wise-owner@example.test', 'password' => 'TestOnly-Password-42!', 'staff_role_id' => StaffRole::where('name', 'owner')->value('id'), 'two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()]);
    }

    private function guest(string $method, string $path, array $v = [], ?string $token = null)
    {
        return $this->json($method, '/api/v1/commerce/'.$path, $v, ['Origin' => config('commerce.storefront_origin'), 'Authorization' => 'Bearer '.($token ?? $this->token)]);
    }

    private function input(): array
    {
        $bag = $this->guest('GET', 'bag')->assertOk()->json('data');
        $this->guest('POST', 'bag/items', ['variant_id' => $this->variant, 'quantity' => 1, 'revision' => $bag['revision']])->assertOk();
        $bag = $this->guest('GET', 'bag')->json('data');
        $d = F::details($bag['revision']);
        $q = $this->guest('POST', 'quote', $d)->assertOk()->json('data');

        return $d + ['attempt_id' => (string) Str::uuid(), 'quote_hash' => $q['quote_hash'], 'terms' => true];
    }

    private function order(): array
    {
        return $this->guest('POST', 'checkout', $this->input())->assertOk()->json('data');
    }

    private function evidence(array $o, string $receipt = 'WISE-QA-123'): array
    {
        return ['operation_id' => (string) Str::uuid(), 'confirmed' => true, 'amount_minor' => $o['total_minor'], 'currency' => 'GBP', 'payment_reference' => $o['reference'], 'receipt_reference' => $receipt];
    }

    private function staff(string $path, array $v)
    {
        $this->owner->refresh();
        $this->actingAs($this->owner, 'staff');
        $this->withSession(['staff_version' => $this->owner->auth_version, 'staff_authenticated_at' => now()->timestamp, 'staff_seen_at' => now()->timestamp, '_token' => 'wise-csrf']);

        return $this->json('POST', '/api/v1/staff/'.$path, $v, ['Origin' => config('staff.admin_origin'), 'X-CSRF-TOKEN' => 'wise-csrf']);
    }

    public function test_missing_receiving_details_disable_checkout_without_reserving_stock(): void
    {
        $v = $this->input();
        foreach (['enabled' => false, 'account_holder' => '', 'sort_code' => '123', 'account_number' => '', 'window_hours' => 0] as $key => $bad) {
            $old = config('commerce.wise.'.$key);
            config(['commerce.wise.'.$key => $bad]);
            $this->guest('GET', 'bag')->assertJsonPath('data.checkout_enabled', false);
            $this->guest('POST', 'checkout', $v)->assertStatus(503);
            config(['commerce.wise.'.$key => $old]);
        }
        $this->assertDatabaseCount('commerce_orders', 0);
        $this->assertSame(0, (int) DB::table('stock_balances')->sum('reserved'));
        Http::assertNothingSent();
    }

    public function test_wise_always_available_checkout_does_not_reserve_or_commit_stock(): void
    {
        config(['catalogue.track_stock' => false]);
        DB::table('stock_balances')->delete();
        $o = $this->order();
        $this->assertDatabaseCount('commerce_reservations', 0);
        $this->staff('orders/'.$o['id'].'/confirm-wise-payment', $this->evidence($o))->assertOk();
        $this->assertDatabaseHas('commerce_orders', ['id' => $o['id'], 'payment_state' => 'paid']);
        $this->assertDatabaseCount('stock_balances', 0);
        $this->assertDatabaseCount('commerce_stock_events', 0);
        Http::assertNothingSent();
    }

    public function test_checkout_is_owned_idempotent_and_freezes_encrypted_instructions(): void
    {
        $v = $this->input();
        $o = $this->guest('POST', 'checkout', $v)->assertOk()->assertJsonPath('data.payment_method', 'wise_bank_transfer')->assertJsonPath('data.payment_state', 'awaiting_bank_transfer')->json('data');
        $this->guest('POST', 'checkout', $v)->assertJsonPath('data.id', $o['id']);
        $this->assertDatabaseCount('commerce_orders', 1);
        $this->assertSame(1, (int) DB::table('stock_balances')->sum('reserved'));
        $this->assertSame(10, (int) DB::table('stock_balances')->sum('on_hand'));
        $this->assertNull($o['checkout_url']);
        $this->assertSame($o['reference'], $o['payment_instructions']['payment_reference']);
        $this->assertSame($o['total_minor'], $o['payment_instructions']['amount_minor']);
        $this->assertStringNotContainsString('12345678', DB::table('wise_bank_transfers')->value('instructions'));
        config(['commerce.wise.account_number' => '87654321']);
        $this->guest('GET', 'orders/'.$o['id'])->assertJsonPath('data.payment_instructions.account_number', '12345678')->assertJsonMissingPath('data.wise_confirmation');
        $other = app(Bags::class)->create()['token'];
        $this->guest('GET', 'orders/'.$o['id'], [], $other)->assertNotFound();
        $this->guest('POST', 'orders/'.$o['id'].'/resume')->assertJsonPath('data.payment_state', 'awaiting_bank_transfer');
        $this->guest('POST', 'checkout', array_replace($v, ['terms' => false]))->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_owner_confirmation_commits_stock_and_replays_without_duplicate_effects(): void
    {
        $o = $this->order();
        $v = $this->evidence($o);
        $path = 'orders/'.$o['id'].'/confirm-wise-payment';
        $this->staff($path, $v)->assertOk()->assertJsonPath('data.payment_state', 'paid')->assertJsonPath('data.order_state', 'confirmed')->assertJsonPath('data.wise_confirmation.receipt_reference', 'WISE-QA-123');
        $this->staff($path, $v)->assertOk();
        $this->assertSame(9, (int) DB::table('stock_balances')->sum('on_hand'));
        $this->assertSame(0, (int) DB::table('stock_balances')->sum('reserved'));
        $this->assertDatabaseCount('commerce_mail_outbox', 1);
        $this->assertSame(1, DB::table('commerce_stock_events')->where('kind', 'commit')->count());
        $this->assertSame(1, DB::table('staff_audit_events')->where('action', 'payment.wise_receipt_confirmed')->count());
        $this->staff($path, array_replace($v, ['receipt_reference' => 'CHANGED']))->assertConflict();
        $this->guest('GET', 'orders/'.$o['id'])->assertJsonPath('data.payment_instructions', null)->assertJsonMissingPath('data.wise_confirmation');
        $this->guest('POST', 'orders/'.$o['id'].'/resume')->assertJsonPath('data.payment_state', 'paid');
        $this->assertNull(DB::table('commerce_bags')->where('active_order_id', $o['id'])->first());
        $this->assertNotSame('WISE-QA-123', DB::table('wise_bank_transfers')->value('receipt_reference'));
        Http::assertNothingSent();
    }

    public function test_wrong_amount_reference_currency_acknowledgement_and_duplicate_receipts_are_rejected(): void
    {
        $o = $this->order();
        $v = $this->evidence($o);
        foreach ([['amount_minor' => $o['total_minor'] - 1], ['amount_minor' => $o['total_minor'] + 1], ['payment_reference' => 'WRONG'], ['currency' => 'EUR'], ['confirmed' => false], ['receipt_reference' => '']] as $bad) {
            $this->staff('orders/'.$o['id'].'/confirm-wise-payment', array_replace($v, $bad))->assertUnprocessable();
        }
        $this->assertSame(1, (int) DB::table('stock_balances')->sum('reserved'));
        $this->staff('orders/'.$o['id'].'/confirm-wise-payment', $v)->assertOk();
        $this->token = app(Bags::class)->create()['token'];
        $second = $this->order();
        $this->staff('orders/'.$second['id'].'/confirm-wise-payment', $this->evidence($second))->assertUnprocessable();
        $this->assertDatabaseHas('commerce_orders', ['id' => $second['id'], 'payment_state' => 'awaiting_bank_transfer']);
        $this->assertDatabaseCount('commerce_mail_outbox', 1);
    }

    public function test_unprivileged_staff_and_guests_cannot_confirm_receipts(): void
    {
        $o = $this->order();
        $v = $this->evidence($o);
        $path = 'orders/'.$o['id'].'/confirm-wise-payment';
        $this->postJson('/api/v1/staff/'.$path, $v)->assertStatus(419);
        $this->withSession(['_token' => 'wise-guest']);
        $this->json('POST', '/api/v1/staff/'.$path, $v, ['Origin' => config('staff.admin_origin'), 'X-CSRF-TOKEN' => 'wise-guest'])->assertUnauthorized();
        foreach (['operations', 'support', 'finance'] as $role) {
            $this->owner->update(['staff_role_id' => StaffRole::where('name', $role)->value('id')]);
            $this->staff($path, $v)->assertForbidden();
        }
        $this->assertDatabaseHas('commerce_orders', ['id' => $o['id'], 'payment_state' => 'awaiting_bank_transfer']);
        $this->assertSame(10, (int) DB::table('stock_balances')->sum('on_hand'));
    }

    public function test_expiry_releases_once_and_late_or_paid_orders_cannot_be_reconfirmed(): void
    {
        $o = $this->order();
        $v = $this->evidence($o);
        $this->travel(25)->hours();
        $this->staff('orders/'.$o['id'].'/confirm-wise-payment', $v)->assertUnprocessable();
        $this->guest('POST', 'orders/'.$o['id'].'/resume')->assertJsonPath('data.payment_state', 'expired')->assertJsonPath('data.payment_instructions', null);
        app(WiseBankTransfer::class)->expire($o['id']);
        $this->artisan('commerce:wise-maintenance')->assertExitCode(0);
        $this->assertSame(0, (int) DB::table('stock_balances')->sum('reserved'));
        $this->assertSame(10, (int) DB::table('stock_balances')->sum('on_hand'));
        $this->assertSame(1, DB::table('commerce_stock_events')->where('kind', 'release')->count());
        $this->staff('orders/'.$o['id'].'/confirm-wise-payment', $v)->assertUnprocessable();
        Http::assertNothingSent();
        $this->travelBack();
    }

    public function test_fulfilment_waits_for_payment_and_manual_wise_orders_keep_their_provider(): void
    {
        $o = $this->order();
        try {
            app(Shipments::class)->order($o['id']);
            $this->fail('Unpaid order was eligible for fulfilment');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('order', $e->errors());
        }
        $this->staff('orders/'.$o['id'].'/confirm-wise-payment', $this->evidence($o))->assertOk();
        $this->assertSame('confirmed', app(Shipments::class)->order($o['id'])->order_state);
        $d = F::details(1) + ['items' => [['variant_id' => $this->variant, 'quantity' => 1]]];
        $q = $this->staff('orders/manual/quote', $d)->assertOk()->json('data');
        $manual = $this->staff('orders/manual/create', $d + ['quote_hash' => $q['quote_hash'], 'operation_id' => (string) Str::uuid(), 'confirmed' => true])->assertOk()->assertJsonPath('data.payment_method', 'wise_bank_transfer')->assertJsonPath('data.payment_state', 'awaiting_bank_transfer')->json('data');
        $this->assertDatabaseHas('manual_order_attempts', ['order_id' => $manual['id']]);
        $this->assertDatabaseHas('order_access_links', ['order_id' => $manual['id'], 'purpose' => 'payment']);
        Http::assertNothingSent();
    }

    public function test_wise_funds_cannot_be_refunded_by_the_local_refund_simulator(): void
    {
        $o = $this->order();
        $this->staff('orders/'.$o['id'].'/confirm-wise-payment', $this->evidence($o))->assertOk();
        $id = (string) Str::uuid();
        DB::table('after_sales_returns')->insert(['id' => $id, 'order_id' => $o['id'], 'order_item_id' => DB::table('commerce_order_items')->where('order_id', $o['id'])->value('id'), 'fingerprint' => str_repeat('a', 64), 'quantity' => 1, 'units' => '[1]', 'credit_minor' => 6800, 'reason' => 'Test return', 'resolution' => 'refund', 'comments' => '', 'state' => 'accepted', 'created_at' => now(), 'updated_at' => now()]);
        try {
            app(Refunds::class)->start($id, ['operation_id' => (string) Str::uuid(), 'amount_minor' => 6800, 'reason' => 'Test refund', 'scenario' => 'success'], $this->owner->id);
            $this->fail('A Wise payment reached the refund simulator');
        } catch (HttpException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }
        $this->assertDatabaseCount('refund_attempts', 0);
        $this->assertDatabaseCount('sandbox_refund_receipts', 0);
    }

    public function test_stripe_events_cannot_confirm_a_wise_order(): void
    {
        $o = $this->order();
        config(['commerce.webhook_secret' => 'whsec_wise_guard_test']);
        $body = json_encode(['id' => 'evt_wise_guard', 'type' => 'checkout.session.completed', 'livemode' => false, 'data' => ['object' => ['id' => 'cs_test_other', 'metadata' => ['order_id' => $o['id']]]]]);
        $time = time();
        $signature = hash_hmac('sha256', $time.'.'.$body, config('commerce.webhook_secret'));
        $this->call('POST', '/api/v1/payments/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.$time.',v1='.$signature], $body)->assertBadRequest();
        $this->assertDatabaseHas('commerce_orders', ['id' => $o['id'], 'payment_state' => 'awaiting_bank_transfer']);
        $this->assertDatabaseCount('commerce_webhook_events', 0);
        $this->assertDatabaseCount('commerce_mail_outbox', 0);
        Http::assertNothingSent();
    }
}
