<?php

namespace Tests\Feature;

use App\AfterSales\Exchanges;
use App\AfterSales\Refunds;
use App\AfterSales\Returns;
use App\Commerce\Bags;
use App\Commerce\Checkout;
use App\Commerce\Pricing;
use App\Fulfilment\Shipments;
use App\Fulfilment\Tracking;
use App\Models\StaffRole;
use App\Models\StaffUser;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\CommerceFixture as F;
use Tests\TestCase;

class AfterSalesTest extends TestCase
{
    use RefreshDatabase;

    private int $variant;

    private StaffUser $staff;

    private array $order;

    private ?array $provider = null;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['commerce.enabled' => true, 'commerce.secret' => 'sk_test_contract_only', 'commerce.webhook_secret' => 'whsec_contract_only', 'fulfilment.webhook_secret' => str_repeat('a', 48), 'fulfilment.scenario' => 'normal']);
        Http::preventStrayRequests();
        Http::fake(function ($r) {
            if ($r->method() === 'POST') {
                $this->provider = F::session($r->data());
            }

            return Http::response($this->provider);
        });
        $this->variant = F::seed(20);
        $this->staff = StaffUser::create(['name' => 'QA Owner', 'email' => 'after@example.test', 'password' => 'TestOnly-Password-42!', 'staff_role_id' => StaffRole::where('name', 'owner')->value('id'), 'two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()]);
        $this->staff->refresh();
        $this->actingAs($this->staff, 'staff');
        $this->withSession(['staff_version' => $this->staff->auth_version, 'staff_authenticated_at' => now()->timestamp, 'staff_seen_at' => now()->timestamp, '_token' => 'phase8']);
        $b = app(Bags::class)->create();
        $r = Request::create('/', 'POST');
        $r->headers->set('Authorization', 'Bearer '.$b['token']);
        app(Bags::class)->change($r, ['variant_id' => $this->variant, 'quantity' => 2, 'revision' => 1]);
        $d = F::details();
        $q = app(Pricing::class)->quote(app(Bags::class)->owned($r), $d);
        $this->order = app(Checkout::class)->start($r, $d + ['attempt_id' => (string) Str::uuid(), 'quote_hash' => $q['quote_hash'], 'terms' => true]);
        $this->provider['status'] = 'complete';
        $this->provider['payment_status'] = 'paid';
        app(Checkout::class)->reconcile($this->order['id']);
    }

    private function customer(string $path, ?array $v = null, ?string $token = null)
    {
        return $this->json($v === null ? 'GET' : 'POST', '/api/v1/customer/'.$path, $v ?? [], ['Origin' => config('commerce.storefront_origin')] + ($token ? ['Authorization' => 'Bearer '.$token] : []));
    }

    private function admin(string $path, ?array $v = null)
    {
        return $this->json($v === null ? 'GET' : 'POST', '/api/v1/staff/after-sales/'.$path, $v ?? [], ['Origin' => config('staff.admin_origin'), 'X-CSRF-TOKEN' => 'phase8']);
    }

    private function token(string $purpose): string
    {
        $h = DB::table('customer_notifications')->whereIn('id', DB::table('customer_challenges')->whereNull('used_at')->select('id'))->orderByDesc('created_at')->orderByDesc('id')->get()->first(function ($n) use ($purpose) {
            return json_decode(Crypt::decryptString($n->encrypted_payload), true)['purpose'] === $purpose;
        });
        $p = json_decode(Crypt::decryptString($h->encrypted_payload), true);
        parse_str(parse_url($p['url'], PHP_URL_FRAGMENT), $f);

        return $f['token'];
    }

    private function account(string $email = 'a@example.test'): string
    {
        $this->customer('register', ['name' => 'QA Customer', 'email' => $email, 'password' => 'Customer-Test-Password-42!', 'terms' => true])->assertOk();
        $this->customer('redeem', ['purpose' => 'verify', 'token' => $this->token('verify')])->assertOk();

        return $this->customer('login', ['email' => $email, 'password' => 'Customer-Test-Password-42!'])->assertOk()->json('data.token');
    }

    private function linked(string $token): void
    {
        $this->customer('claim', ['reference' => $this->order['reference']], $token)->assertOk();
        $this->customer('redeem', ['purpose' => 'claim', 'token' => $this->token('claim')], $token)->assertOk();
    }

    private function delivered(): void
    {
        $q = app(Shipments::class)->quote($this->order['id'], ['items' => [['order_item_id' => DB::table('commerce_order_items')->where('order_id', $this->order['id'])->value('id'), 'quantity' => 2]], 'location_id' => DB::table('stock_locations')->value('id'), 'service' => 'sandbox_standard', 'parcel' => ['weight_grams' => 500, 'length_cm' => 30, 'width_cm' => 24, 'height_cm' => 8], 'instructions' => 'Test'], $this->staff->id);
        $f = app(Shipments::class)->prepare($this->order['id'], $q['id'], $this->staff->id);
        app(Shipments::class)->book($f->id, $this->staff->id);
        app(Shipments::class)->dispatch($f->id, $this->staff->id);
        DB::table('order_fulfilments')->where('id', $f->id)->update(['state' => 'delivered', 'last_event_at' => now()]);
    }

    private function request(string $resolution = 'refund', int $qty = 1): array
    {
        $o = DB::table('commerce_orders')->find($this->order['id']);
        $v = ['operation_id' => (string) Str::uuid(), 'order_item_id' => DB::table('commerce_order_items')->where('order_id', $o->id)->value('id'), 'quantity' => $qty, 'reason' => 'size', 'resolution' => $resolution, 'exchange_variant_id' => $resolution === 'exchange' ? $this->variant : null, 'comments' => 'Partial test request', 'confirmed' => true];
        $r = Request::create('/', 'POST', $v);

        return app(Returns::class)->request($o, $r);
    }

    private function inspection(array $r, string $outcome = 'accepted_resellable'): array
    {
        return ['received_quantity' => $r['quantity'], 'location_id' => DB::table('stock_locations')->value('id'), 'outcome' => $outcome, 'reason' => 'Physically inspected test units', 'confirmed' => true];
    }

    private function accepted(string $resolution = 'refund'): array
    {
        $this->delivered();
        $r = $this->request($resolution);

        return app(Returns::class)->inspect($r['id'], $this->inspection($r), $this->staff->id);
    }

    private function refund(array $r, string $scenario = 'succeed', ?int $amount = null): array
    {
        return ['operation_id' => (string) Str::uuid(), 'amount_minor' => $amount ?? $r['credit_minor'], 'reason' => 'Approved test merchandise credit', 'scenario' => $scenario, 'confirmed' => true];
    }

    public function test_registration_verification_neutral_and_single_use(): void
    {
        $v = ['name' => 'A', 'email' => 'a@example.test', 'password' => 'Customer-Test-Password-42!', 'terms' => true];
        $this->customer('register', $v)->assertOk();
        $this->customer('register', $v)->assertOk();
        $this->assertDatabaseCount('customers', 1);
        $this->customer('login', ['email' => $v['email'], 'password' => $v['password']])->assertUnauthorized();
        $t = $this->token('verify');
        $this->customer('redeem', ['purpose' => 'verify', 'token' => $t])->assertOk();
        $this->customer('redeem', ['purpose' => 'verify', 'token' => $t])->assertUnauthorized();
        $this->assertDatabaseCount('customer_notifications', 1);
    }

    public function test_staff_cookie_never_authorizes_customer_and_private_headers(): void
    {
        $this->customer('me')->assertUnauthorized()->assertHeader('Cache-Control', 'no-store, private');
        $this->customer('orders')->assertUnauthorized();
    }

    public function test_guest_order_claim_requires_original_email_challenge_and_bound_customer(): void
    {
        $a = $this->account();
        $this->customer('order?id='.$this->order['id'], null, $a)->assertNotFound();
        $this->customer('claim', ['reference' => $this->order['reference']], $a)->assertOk();
        $t = $this->token('claim');
        $n = DB::table('customer_notifications')->where('id', DB::table('customer_challenges')->where('purpose', 'claim')->value('id'))->first();
        $this->assertSame('sandbox-guest@example.test', json_decode(Crypt::decryptString($n->encrypted_payload), true)['email']);
        $b = $this->account('b@example.test');
        $this->customer('redeem', ['purpose' => 'claim', 'token' => $t], $b)->assertUnauthorized();
        $this->customer('redeem', ['purpose' => 'claim', 'token' => $t], $a)->assertOk();
        $this->customer('order?id='.$this->order['id'], null, $a)->assertOk()->assertJsonMissingPath('data.reservations');
        $this->customer('order?id='.$this->order['id'], null, $b)->assertNotFound();
    }

    public function test_expired_link_and_session_are_rejected(): void
    {
        $t = $this->account();
        DB::table('customer_sessions')->update(['seen_at' => now()->subHours(3)]);
        $this->customer('me', null, $t)->assertUnauthorized();
        $this->customer('recover', ['email' => 'a@example.test'])->assertOk();
        DB::table('customer_challenges')->where('purpose', 'reset')->update(['expires_at' => now()->subSecond()]);
        $this->customer('redeem', ['purpose' => 'reset', 'token' => $this->token('reset'), 'password' => 'Replacement-Password-42!'])->assertUnauthorized();
    }

    public function test_recovery_revokes_sessions_and_old_reset_links(): void
    {
        $t = $this->account();
        $this->customer('recover', ['email' => 'a@example.test'])->assertOk();
        $link = $this->token('reset');
        $this->customer('redeem', ['purpose' => 'reset', 'token' => $link, 'password' => 'Replacement-Password-42!'])->assertOk();
        $this->customer('me', null, $t)->assertUnauthorized();
        $this->customer('redeem', ['purpose' => 'reset', 'token' => $link, 'password' => 'Replacement-Password-42!'])->assertUnauthorized();
        $this->customer('login', ['email' => 'a@example.test', 'password' => 'Replacement-Password-42!'])->assertOk();
    }

    public function test_address_isolation_versioning_and_order_snapshot_unchanged(): void
    {
        $a = $this->account();
        $v = ['action' => 'save', 'address' => F::details()['address'], 'is_default' => true, 'confirmed' => true];
        $before = DB::table('commerce_order_snapshots')->value('payload');
        $saved = $this->customer('addresses', $v, $a)->assertOk()->json('data.0');
        $b = $this->account('b@example.test');
        $this->customer('addresses', $v + ['id' => $saved['id'], 'version' => 1], $b)->assertNotFound();
        $this->customer('addresses', $v + ['id' => $saved['id'], 'version' => 1], $a)->assertOk();
        $this->customer('addresses', $v + ['id' => $saved['id'], 'version' => 1], $a)->assertUnprocessable();
        $this->assertSame($before, DB::table('commerce_order_snapshots')->value('payload'));
    }

    public function test_wishlist_union_preserves_account_choices_and_scope(): void
    {
        $a = $this->account();
        $product = DB::table('catalogue_variants')->where('id', $this->variant)->value('product_id');
        $v = ['action' => 'merge', 'items' => [['product_id' => $product, 'variant_id' => $this->variant]]];
        $this->customer('saved', $v, $a)->assertOk()->assertJsonCount(1, 'data');
        $this->customer('saved', ['action' => 'merge', 'items' => []], $a)->assertOk()->assertJsonCount(1, 'data');
        $b = $this->account('b@example.test');
        $this->customer('saved', null, $b)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_unverified_delivery_cannot_return(): void
    {
        $this->expectException(ValidationException::class);
        $this->request();
    }

    public function test_partial_unit_claims_exact_credit_and_quantity_cap(): void
    {
        $this->delivered();
        $a = $this->request();
        $b = $this->request();
        $s = app(Checkout::class)->snapshot($this->order['id']);
        $this->assertSame($s['subtotal_minor'] - $s['discount_minor'], $a['credit_minor'] + $b['credit_minor']);
        $this->assertDatabaseCount('return_unit_claims', 2);
        $this->expectException(ValidationException::class);
        $this->request();
    }

    public function test_old_delivery_cannot_return(): void
    {
        $this->delivered();
        DB::table('order_fulfilments')->update(['last_event_at' => now()->subDays(31)]);
        $this->expectException(ValidationException::class);
        $this->request();
    }

    public function test_return_idempotent_replay_and_changed_payload_collision(): void
    {
        $this->delivered();
        $r = $this->request();
        $saved = DB::table('after_sales_returns')->find($r['id']);
        $v = ['operation_id' => $r['id'], 'order_item_id' => $r['order_item_id'], 'quantity' => 1, 'reason' => 'size', 'resolution' => 'refund', 'exchange_variant_id' => null, 'comments' => 'Partial test request', 'confirmed' => true];
        $this->assertSame($r['id'], app(Returns::class)->request(DB::table('commerce_orders')->find($this->order['id']), Request::create('/', 'POST', $v))['id']);
        $this->assertDatabaseCount('return_unit_claims', 1);
        $v['quantity'] = 2;
        $this->expectException(HttpException::class);
        app(Returns::class)->request(DB::table('commerce_orders')->find($this->order['id']), Request::create('/', 'POST', $v));
    }

    public function test_resellable_inspection_restocks_exactly_once_and_is_immutable(): void
    {
        $this->delivered();
        $r = $this->request();
        $before = DB::table('stock_balances')->value('on_hand');
        $v = $this->inspection($r);
        $this->admin($r['id'].'/inspect', $v)->assertOk();
        $this->admin($r['id'].'/inspect', $v)->assertOk();
        $this->assertSame($before + 1, DB::table('stock_balances')->value('on_hand'));
        $this->assertDatabaseCount('after_sales_stock_events', 1);
        $this->expectException(QueryException::class);
        DB::table('return_inspections')->update(['reason' => 'rewrite']);
    }

    public function test_damaged_does_not_restock_and_rejected_releases_claim(): void
    {
        $this->delivered();
        $a = $this->request();
        $b = $this->request();
        $before = DB::table('stock_balances')->value('on_hand');
        app(Returns::class)->inspect($a['id'], $this->inspection($a, 'accepted_damaged'), $this->staff->id);
        app(Returns::class)->inspect($b['id'], $this->inspection($b, 'rejected'), $this->staff->id);
        $this->assertSame($before, DB::table('stock_balances')->value('on_hand'));
        $this->assertDatabaseCount('return_unit_claims', 1);
        $this->assertSame(1, $this->request()['quantity']);
    }

    public function test_refund_requires_inspection_and_cannot_exceed_credit(): void
    {
        $this->delivered();
        $r = $this->request();
        $this->admin($r['id'].'/refund', $this->refund($r))->assertUnprocessable();
        app(Returns::class)->inspect($r['id'], $this->inspection($r), $this->staff->id);
        $this->admin($r['id'].'/refund', $this->refund($r, 'succeed', $r['credit_minor'] + 1))->assertUnprocessable();
    }

    public function test_refund_partial_replay_caps_and_never_restocks(): void
    {
        $r = $this->accepted();
        $before = DB::table('stock_balances')->value('on_hand');
        $v = $this->refund($r, 'succeed', 2000);
        $a = $this->admin($r['id'].'/refund', $v)->assertOk()->assertJsonPath('data.state', 'sandbox_succeeded');
        $this->admin($r['id'].'/refund', $v)->assertOk();
        $this->assertDatabaseCount('sandbox_refund_receipts', 1);
        $this->admin($r['id'].'/refund', $this->refund($r, 'succeed', $r['credit_minor'] - 2000 + 1))->assertUnprocessable();
        $this->admin($r['id'].'/refund', $this->refund($r, 'succeed', $r['credit_minor'] - 2000))->assertOk();
        $this->assertSame($before, DB::table('stock_balances')->value('on_hand'));
    }

    public function test_unknown_refund_retries_same_provider_receipt(): void
    {
        $r = $this->accepted();
        $v = $this->refund($r, 'timeout_after_once');
        $this->admin($r['id'].'/refund', $v)->assertOk()->assertJsonPath('data.state', 'sandbox_unknown');
        $this->assertSame(0, app(Refunds::class)->remaining(DB::table('after_sales_returns')->find($r['id'])));
        $this->admin($v['operation_id'].'/retry', ['confirmed' => true])->assertOk()->assertJsonPath('data.attempt.state', 'sandbox_succeeded');
        $this->assertDatabaseCount('sandbox_refund_receipts', 1);
    }

    public function test_pending_failure_reconciliation_releases_credit(): void
    {
        $r = $this->accepted();
        $v = $this->refund($r, 'pending');
        $this->admin($r['id'].'/refund', $v)->assertOk()->assertJsonPath('data.state', 'sandbox_pending');
        $this->admin($r['id'].'/refund', $this->refund($r))->assertUnprocessable();
        DB::table('sandbox_refund_receipts')->where('attempt_id', $v['operation_id'])->update(['state' => 'failed']);
        app(Refunds::class)->process($v['operation_id']);
        $this->assertSame($r['credit_minor'], app(Refunds::class)->remaining(DB::table('after_sales_returns')->find($r['id'])));
    }

    public function test_mismatched_receipt_never_marks_success(): void
    {
        $r = $this->accepted();
        $v = $this->refund($r, 'pending');
        $this->admin($r['id'].'/refund', $v)->assertOk();
        DB::table('sandbox_refund_receipts')->where('attempt_id', $v['operation_id'])->update(['amount_minor' => 1, 'state' => 'succeeded']);
        app(Refunds::class)->process($v['operation_id']);
        $this->assertDatabaseHas('refund_attempts', ['id' => $v['operation_id'], 'state' => 'sandbox_pending']);
        $this->assertSame(0, app(Refunds::class)->remaining(DB::table('after_sales_returns')->find($r['id'])));
    }

    public function test_exchange_topup_is_unpaid_cannot_ship_and_expires(): void
    {
        $r = $this->accepted('exchange');
        $v = ['operation_id' => (string) Str::uuid(), 'variant_id' => $this->variant, 'expected_difference_minor' => 6800 - $r['credit_minor'], 'confirmed' => true];
        $e = $this->admin($r['id'].'/exchange', $v)->assertOk()->json('data');
        $this->assertSame('awaiting_top_up', $e['state']);
        $this->assertDatabaseHas('commerce_orders', ['id' => $e['replacement_order_id'], 'payment_state' => 'awaiting_exchange_payment', 'paid_at' => null]);
        try {
            app(Shipments::class)->order($e['replacement_order_id']);
            $this->fail('Unpaid exchange was allowed to ship');
        } catch (ValidationException $expected) {
        }$this->travel(31)->minutes();
        app(Exchanges::class)->expire();
        $this->assertDatabaseHas('exchange_links', ['id' => $v['operation_id'], 'state' => 'expired']);
        $this->assertSame(0, (int) DB::table('stock_balances')->sum('reserved'));
    }

    public function test_cheaper_exchange_has_valid_funding_and_residual_refund_only(): void
    {
        $r = $this->accepted('exchange');
        DB::table('catalogue_variants')->where('id', $this->variant)->update(['price_minor' => 5000]);
        $v = ['operation_id' => (string) Str::uuid(), 'variant_id' => $this->variant, 'expected_difference_minor' => 5000 - $r['credit_minor'], 'confirmed' => true];
        $e = $this->admin($r['id'].'/exchange', $v)->assertOk()->json('data');
        $this->assertSame('issued', $e['state']);
        $o = app(Shipments::class)->order($e['replacement_order_id']);
        $this->assertSame('exchange_credit', $o->payment_state);
        $this->assertNull($o->paid_at);
        $this->admin($r['id'].'/exchange', $v)->assertOk();
        $this->assertDatabaseCount('exchange_links', 1);
        $this->assertSame($r['credit_minor'] - 5000, app(Refunds::class)->remaining(DB::table('after_sales_returns')->find($r['id'])));
        $this->admin($r['id'].'/refund', $this->refund($r, 'succeed', $r['credit_minor'] - 5000 + 1))->assertUnprocessable();
    }

    public function test_unavailable_exchange_keeps_credit_and_no_order_created(): void
    {
        $r = $this->accepted('exchange');
        DB::table('stock_balances')->update(['on_hand' => 0]);
        $count = DB::table('commerce_orders')->count();
        $this->admin($r['id'].'/exchange', ['operation_id' => (string) Str::uuid(), 'variant_id' => $this->variant, 'expected_difference_minor' => 6800 - $r['credit_minor'], 'confirmed' => true])->assertUnprocessable();
        $this->assertSame($count, DB::table('commerce_orders')->count());
        $this->admin($r['id'].'/resolution', ['reason' => 'No replacement stock', 'confirmed' => true])->assertOk();
        $this->admin($r['id'].'/refund', $this->refund($r))->assertOk();
    }

    public function test_support_cannot_inspect_or_refund_and_csrf_required(): void
    {
        $r = $this->accepted();
        $this->json('POST', '/api/v1/staff/after-sales/'.$r['id'].'/refund', $this->refund($r), ['Origin' => config('staff.admin_origin')])->assertStatus(419);
        $this->staff->staff_role_id = StaffRole::where('name', 'support')->value('id');
        $this->staff->save();
        $this->staff->refresh();
        $this->actingAs($this->staff, 'staff');
        $this->admin($r['id'].'/inspect', $this->inspection($r))->assertForbidden();
        $this->admin($r['id'].'/refund', $this->refund($r))->assertForbidden();
    }

    public function test_customer_return_scope_and_guest_tracking_capabilities_are_separate(): void
    {
        $this->delivered();
        $a = $this->account();
        $this->linked($a);
        $b = $this->account('b@example.test');
        $v = ['order_id' => $this->order['id'], 'operation_id' => (string) Str::uuid(), 'order_item_id' => DB::table('commerce_order_items')->where('order_id', $this->order['id'])->value('id'), 'quantity' => 1, 'reason' => 'size', 'resolution' => 'refund', 'confirmed' => true];
        $this->customer('returns', $v, $b)->assertNotFound();
        $this->json('GET', '/api/v1/tracking/returns', [], ['Authorization' => 'Bearer '.$a])->assertUnauthorized();
        app(Tracking::class)->issue($this->order['id'], 'tracking');
        $n = DB::table('order_notifications')->where('kind', 'tracking_link')->first();
        $payload = json_decode(Crypt::decryptString($n->encrypted_payload), true);
        parse_str(parse_url($payload['url'], PHP_URL_FRAGMENT), $fragment);
        $cap = app(Tracking::class)->redeem($fragment['token'], 'tracking')['token'];
        $this->json('POST', '/api/v1/tracking/returns', $v, ['Origin' => config('commerce.storefront_origin'), 'Authorization' => 'Bearer '.$cap])->assertOk();
        $this->customer('order?id='.$this->order['id'], null, $a)->assertOk()->assertJsonCount(1, 'data.after_sales.returns');
    }

    public function test_logout_revokes_only_customer_capability_and_email_change_revokes_sessions(): void
    {
        $t = $this->account();
        $this->customer('email', ['email' => 'new@example.test', 'password' => 'Wrong-password-42!'], $t)->assertUnauthorized();
        $this->customer('email', ['email' => 'new@example.test', 'password' => 'Customer-Test-Password-42!'], $t)->assertOk();
        $this->customer('redeem', ['purpose' => 'email', 'token' => $this->token('email')], $t)->assertOk();
        $this->customer('me', null, $t)->assertUnauthorized();
        $new = $this->customer('login', ['email' => 'new@example.test', 'password' => 'Customer-Test-Password-42!'])->assertOk()->json('data.token');
        $this->customer('logout', [], $new)->assertOk();
        $this->customer('me', null, $new)->assertUnauthorized();
    }

    public function test_wishlist_overflow_rejects_atomically_and_keeps_existing_items(): void
    {
        $t = $this->account();
        $c = DB::table('customers')->first();
        for ($i = 1; $i <= 500; $i++) {
            DB::table('customer_saved_items')->insert(['customer_id' => $c->id, 'product_id' => $i, 'variant_id' => null, 'created_at' => now()]);
        }$this->customer('saved', ['action' => 'merge', 'items' => [['product_id' => 501, 'variant_id' => null]]], $t)->assertUnprocessable();
        $this->assertDatabaseCount('customer_saved_items', 500);
    }

    public function test_refund_collision_does_not_change_previously_approved_amount(): void
    {
        $r = $this->accepted();
        $v = $this->refund($r, 'pending', 1000);
        $this->admin($r['id'].'/refund', $v)->assertOk();
        $v['amount_minor'] = 2000;
        $this->admin($r['id'].'/refund', $v)->assertConflict();
        $this->assertDatabaseHas('refund_attempts', ['id' => $v['operation_id'], 'amount_minor' => 1000]);
        $this->assertDatabaseCount('sandbox_refund_receipts', 1);
    }

    public function test_partial_inspection_cannot_restock_or_approve_full_request(): void
    {
        $this->delivered();
        $r = $this->request('refund', 2);
        $v = $this->inspection($r);
        $v['received_quantity'] = 1;
        $this->admin($r['id'].'/inspect', $v)->assertUnprocessable();
        $this->assertDatabaseCount('return_inspections', 0);
        $this->assertDatabaseCount('after_sales_stock_events', 0);
    }

    public function test_exchange_price_change_requires_new_explicit_review(): void
    {
        $r = $this->accepted('exchange');
        $this->admin($r['id'].'/exchange', ['operation_id' => (string) Str::uuid(), 'variant_id' => $this->variant, 'expected_difference_minor' => 0, 'confirmed' => true])->assertUnprocessable();
        $this->assertDatabaseCount('exchange_links', 0);
        $this->assertSame(0, (int) DB::table('stock_balances')->sum('reserved'));
    }

    public function test_finance_can_review_refund_without_fulfilment_management(): void
    {
        $r = $this->accepted();
        $this->staff->staff_role_id = StaffRole::where('name', 'finance')->value('id');
        $this->staff->save();
        $this->staff->refresh();
        $this->actingAs($this->staff, 'staff');
        $this->admin('returns')->assertForbidden();
        $this->admin('refunds')->assertOk();
        $this->admin($r['id'].'/refund', $this->refund($r, 'fail', 1000))->assertOk()->assertJsonPath('data.state', 'sandbox_failed');
        $this->assertSame($r['credit_minor'], app(Refunds::class)->remaining(DB::table('after_sales_returns')->find($r['id'])));
    }

    public function test_live_environment_refunds_fail_closed(): void
    {
        $r = $this->accepted();
        $this->app->instance('env', 'production');
        $this->admin($r['id'].'/refund', $this->refund($r))->assertStatus(503);
        $this->assertDatabaseCount('refund_attempts', 0);
        $this->admin($r['id'].'/resolution', ['reason' => 'Test policy cannot run live', 'confirmed' => true])->assertStatus(503);
    }

    public function test_cancelled_earlier_parcel_cannot_make_an_already_claimed_delivery_eligible_again(): void
    {
        $parcel = ['items' => [['order_item_id' => DB::table('commerce_order_items')->where('order_id', $this->order['id'])->value('id'), 'quantity' => 1]], 'location_id' => DB::table('stock_locations')->value('id'), 'service' => 'sandbox_standard', 'parcel' => ['weight_grams' => 500, 'length_cm' => 30, 'width_cm' => 24, 'height_cm' => 8], 'instructions' => ''];
        $make = function () use ($parcel) {
            $q = app(Shipments::class)->quote($this->order['id'], $parcel, $this->staff->id);
            $f = app(Shipments::class)->prepare($this->order['id'], $q['id'], $this->staff->id);
            app(Shipments::class)->book($f->id, $this->staff->id);

            return $f;
        };
        $earlier = $make();
        $delivered = $make();
        app(Shipments::class)->dispatch($delivered->id, $this->staff->id);
        DB::table('order_fulfilments')->where('id', $delivered->id)->update(['state' => 'delivered', 'last_event_at' => now()]);
        $a = $this->request();
        $item = DB::table('commerce_order_items')->find($parcel['items'][0]['order_item_id']);
        $this->assertSame([], app(Returns::class)->units($item));
        app(Shipments::class)->cancel($earlier->id, $this->staff->id);
        $this->assertSame([], app(Returns::class)->units($item));
        $new = $make();
        app(Shipments::class)->dispatch($new->id, $this->staff->id);
        DB::table('order_fulfilments')->where('id', $new->id)->update(['state' => 'delivered', 'last_event_at' => now()]);
        $b = $this->request();
        $this->assertDatabaseCount('return_unit_claims', 2);
        $this->assertSame(2, DB::table('return_unit_claims')->distinct()->count('fulfilment_item_id'));
        $this->assertSame(12240, $a['credit_minor'] + $b['credit_minor']);
        $this->assertSame([], app(Returns::class)->units($item));
    }
}
