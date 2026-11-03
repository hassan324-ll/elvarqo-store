<?php

namespace Tests\Feature;

use App\Commerce\Bags;
use App\Commerce\Checkout;
use App\Commerce\Pricing;
use App\Fulfilment\Shipments;
use App\Jobs\DeliverOrderNotification;
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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CommerceFixture as F;
use Tests\TestCase;

class FulfilmentTest extends TestCase
{
    use RefreshDatabase;

    private StaffUser $staff;

    private int $variant;

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
        $this->staff = StaffUser::create(['name' => 'QA Operations', 'email' => 'phase7@example.test', 'password' => 'TestOnly-Password-42!', 'staff_role_id' => StaffRole::where('name', 'owner')->value('id'), 'two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()]);
        $this->staff->refresh();
        $this->login();
        $this->order = $this->paidOrder(2);
    }

    private function login(): void
    {
        $this->actingAs($this->staff, 'staff');
        $this->withSession(['staff_version' => $this->staff->auth_version, 'staff_authenticated_at' => now()->timestamp, 'staff_seen_at' => now()->timestamp, '_token' => 'phase7-csrf']);
    }

    private function staff(string $path, ?array $v = null)
    {
        return $this->json($v === null ? 'GET' : 'POST', '/api/v1/staff/'.$path, $v ?? [], ['Origin' => config('staff.admin_origin'), 'X-CSRF-TOKEN' => 'phase7-csrf']);
    }

    private function guest(string $path, ?array $v = null, ?string $token = null)
    {
        return $this->json($v === null ? 'GET' : 'POST', '/api/v1/tracking/'.$path, $v ?? [], ['Origin' => config('commerce.storefront_origin')] + ($token ? ['Authorization' => 'Bearer '.$token] : []));
    }

    private function paidOrder(int $qty = 2): array
    {
        $b = app(Bags::class)->create();
        $r = Request::create('/', 'POST');
        $r->headers->set('Authorization', 'Bearer '.$b['token']);
        app(Bags::class)->change($r, ['variant_id' => $this->variant, 'quantity' => $qty, 'revision' => 1]);
        $d = F::details();
        $q = app(Pricing::class)->quote(app(Bags::class)->owned($r), $d);
        $o = app(Checkout::class)->start($r, $d + ['attempt_id' => (string) Str::uuid(), 'quote_hash' => $q['quote_hash'], 'terms' => true]);
        $this->provider['status'] = 'complete';
        $this->provider['payment_status'] = 'paid';
        app(Checkout::class)->reconcile($o['id']);

        return app(Checkout::class)->view($o['id']);
    }

    private function parcel(int $qty = 1, ?array $order = null): array
    {
        $o = $order ?? $this->order;

        return ['items' => [['order_item_id' => DB::table('commerce_order_items')->where('order_id', $o['id'])->value('id'), 'quantity' => $qty]], 'location_id' => DB::table('stock_locations')->value('id'), 'service' => 'sandbox_standard', 'parcel' => ['weight_grams' => 500, 'length_cm' => 30, 'width_cm' => 24, 'height_cm' => 8], 'instructions' => 'Leave test parcel safely'];
    }

    private function quote(int $qty = 1): array
    {
        return $this->staff('orders/'.$this->order['id'].'/shipment-quotes', $this->parcel($qty))->assertOk()->json('data');
    }

    private function book(int $qty = 1): array
    {
        $q = $this->quote($qty);

        return $this->staff('orders/'.$this->order['id'].'/shipments', ['quote_id' => $q['id'], 'confirmed' => true])->assertOk()->json('data');
    }

    private function trackingToken(): string
    {
        $this->guest('request', ['reference' => $this->order['reference'], 'email' => 'sandbox-guest@example.test'])->assertOk();
        $n = DB::table('order_notifications')->where('kind', 'tracking_link')->orderByDesc('created_at')->first();
        $payload = json_decode(Crypt::decryptString($n->encrypted_payload), true);
        parse_str(parse_url($payload['url'], PHP_URL_FRAGMENT), $fragment);

        return $fragment['token'];
    }

    private function event(array $shipment, string $state, string $id = 'scan-1', ?string $when = null, bool $valid = true)
    {
        $body = json_encode(['id' => $id, 'provider_reference' => $shipment['provider_reference'], 'tracking_number' => $shipment['tracking_number'], 'state' => $state, 'occurred_at' => $when ?? now()->toISOString()]);
        $time = time();
        $signature = hash_hmac('sha256', $time.'.'.$body, config('fulfilment.webhook_secret'));

        return $this->call('POST', '/api/v1/courier/sandbox/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_SANDBOX_SIGNATURE' => 't='.$time.',v1='.($valid ? $signature : 'invalid')], $body);
    }

    public function test_staff_search_and_filters_are_literal_and_states_are_separate(): void
    {
        $this->staff('orders?q='.$this->order['reference'].'&payment=paid&fulfilment=unfulfilled')->assertOk()->assertJsonPath('data.total', 1);
        $this->staff('orders?q='.urlencode('%'))->assertJsonPath('data.total', 0);
        $this->staff('orders?payment=invalid')->assertUnprocessable();
        $this->staff('orders/'.$this->order['id'])->assertJsonPath('data.snapshot.items.0.unit_minor', 6800)->assertJsonPath('data.remaining.0.remaining', 2);
    }

    public function test_support_role_reads_but_cannot_write_notes_book_or_create_manual_orders(): void
    {
        $this->staff->staff_role_id = StaffRole::where('name', 'support')->value('id');
        $this->staff->save();
        $this->staff->unsetRelation('role');
        $this->staff('orders')->assertOk();
        foreach (['orders/'.$this->order['id'].'/notes', 'orders/'.$this->order['id'].'/shipment-quotes', 'orders/'.$this->order['id'].'/shipments', 'orders/manual/create', 'shipments/'.Str::uuid().'/dispatch'] as $path) {
            $this->staff($path, [])->assertForbidden();
        }
    }

    public function test_csrf_is_enforced_on_every_staff_mutation(): void
    {
        $this->json('POST', '/api/v1/staff/orders/'.$this->order['id'].'/notes', ['operation_id' => (string) Str::uuid(), 'body' => 'must be denied'], ['Origin' => config('staff.admin_origin')])->assertStatus(419);
        $this->assertDatabaseCount('order_notes', 0);
    }

    public function test_internal_notes_are_immutable_retry_safe_and_not_in_public_tracking(): void
    {
        $v = ['operation_id' => (string) Str::uuid(), 'body' => 'Private note for dispatch'];
        $this->staff('orders/'.$this->order['id'].'/notes', $v)->assertOk();
        $this->staff('orders/'.$this->order['id'].'/notes', $v)->assertOk();
        $this->assertDatabaseCount('order_notes', 1);
        $this->staff('orders/'.$this->order['id'].'/notes', array_replace($v, ['body' => 'changed']))->assertUnprocessable();
        $this->expectException(QueryException::class);
        DB::table('order_notes')->update(['body' => 'rewritten']);
    }

    public function test_partial_shipments_enforce_remaining_and_do_not_deduct_stock_twice(): void
    {
        $before = DB::table('stock_balances')->value('on_hand');
        $one = $this->book();
        $this->staff('shipments/'.$one['id'].'/dispatch', ['confirmed' => true])->assertOk();
        $this->assertDatabaseHas('commerce_orders', ['id' => $this->order['id'], 'fulfilment_state' => 'partially_fulfilled', 'payment_state' => 'paid']);
        $two = $this->book();
        $this->staff('shipments/'.$two['id'].'/dispatch', ['confirmed' => true])->assertOk();
        $this->assertDatabaseHas('commerce_orders', ['id' => $this->order['id'], 'fulfilment_state' => 'fulfilled', 'payment_state' => 'paid']);
        $this->staff('orders/'.$this->order['id'].'/shipment-quotes', $this->parcel())->assertUnprocessable();
        $this->assertSame($before, DB::table('stock_balances')->value('on_hand'));
        $this->assertDatabaseCount('order_notifications', 2);
    }

    public function test_unpaid_manual_order_is_server_priced_idempotent_and_not_dispatchable(): void
    {
        config(['commerce.enabled' => false]);
        $v = F::details(1) + ['items' => [['variant_id' => $this->variant, 'quantity' => 2]], 'total_minor' => 1];
        unset($v['revision']);
        $q = $this->staff('orders/manual/quote', $v)->assertOk()->assertJsonPath('data.snapshot.total_minor', 12735)->json('data');
        $v += ['operation_id' => (string) Str::uuid(), 'quote_hash' => $q['quote_hash'], 'confirmed' => true];
        $o = $this->staff('orders/manual/create', $v)->assertOk()->assertJsonPath('data.payment_state', 'awaiting_payment_link')->json('data');
        $this->staff('orders/manual/create', $v)->assertOk()->assertJsonPath('data.id', $o['id']);
        $this->staff('orders/manual/create', array_replace($v, ['email' => 'other@example.test']))->assertUnprocessable();
        $this->assertDatabaseCount('manual_order_attempts', 1);
        $this->assertDatabaseCount('order_access_links', 1);
        $this->assertDatabaseHas('stock_balances', ['reserved' => 2]);
        $this->staff('orders/'.$o['id'].'/shipment-quotes', $this->parcel(1, $o))->assertUnprocessable();
        $this->assertDatabaseCount('order_fulfilments', 0);
    }

    public function test_duplicate_quote_booking_creates_one_provider_receipt_and_private_label(): void
    {
        $q = $this->quote();
        $v = ['quote_id' => $q['id'], 'confirmed' => true];
        $one = $this->staff('orders/'.$this->order['id'].'/shipments', $v)->assertOk()->json('data');
        $this->staff('orders/'.$this->order['id'].'/shipments', $v)->assertJsonPath('data.id', $one['id']);
        $this->assertDatabaseCount('sandbox_courier_receipts', 1);
        $this->assertDatabaseCount('fulfilment_labels', 1);
        $this->assertDatabaseCount('courier_booking_attempts', 1);
        $response = $this->staff('shipments/'.$one['id'].'/label')->assertOk();
        $this->assertStringContainsString('NOT VALID FOR POSTAGE', $response->getContent());
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    #[DataProvider('timeouts')]
    public function test_timeout_retries_preserve_identity_and_never_duplicate_provider_labels(string $scenario, int $receipts): void
    {
        config(['fulfilment.scenario' => $scenario]);
        $f = $this->book();
        $this->assertSame('unknown', $f['state']);
        $this->assertDatabaseCount('sandbox_courier_receipts', $receipts);
        $this->assertSame(1, app(Shipments::class)->remaining($this->order['id'])[0]['remaining']);
        $this->staff('shipments/'.$f['id'].'/cancel', ['confirmed' => true])->assertUnprocessable();
        $this->staff('shipments/'.$f['id'].'/retry', ['confirmed' => true])->assertOk()->assertJsonPath('data.state', 'booked');
        $this->staff('shipments/'.$f['id'].'/retry', ['confirmed' => true])->assertOk();
        $this->assertDatabaseCount('sandbox_courier_receipts', 1);
        $this->assertDatabaseCount('fulfilment_labels', 1);
        $this->assertDatabaseCount('courier_booking_attempts', 2);
    }

    public static function timeouts(): array
    {
        return [['timeout_before_once', 0], ['timeout_after_once', 1]];
    }

    public function test_rejected_booking_retains_allocation_until_explicit_eligible_cancellation(): void
    {
        config(['fulfilment.scenario' => 'reject']);
        $f = $this->book();
        $this->assertSame('failed', $f['state']);
        $this->assertDatabaseCount('sandbox_courier_receipts', 0);
        $this->staff('shipments/'.$f['id'].'/dispatch', ['confirmed' => true])->assertUnprocessable();
        $this->staff('shipments/'.$f['id'].'/cancel', ['confirmed' => true])->assertOk()->assertJsonPath('data.state', 'cancelled');
        $this->assertSame(2, app(Shipments::class)->remaining($this->order['id'])[0]['remaining']);
    }

    public function test_booked_label_cancellation_voids_receipt_releases_allocation_without_restocking(): void
    {
        $before = DB::table('stock_balances')->value('on_hand');
        $f = $this->book();
        $this->staff('shipments/'.$f['id'].'/cancel', ['confirmed' => true])->assertOk();
        $this->staff('shipments/'.$f['id'].'/cancel', ['confirmed' => true])->assertOk();
        $this->assertDatabaseHas('sandbox_courier_receipts', ['fulfilment_id' => $f['id'], 'state' => 'void']);
        $this->staff('shipments/'.$f['id'].'/label')->assertNotFound();
        $this->assertSame(2, app(Shipments::class)->remaining($this->order['id'])[0]['remaining']);
        $this->assertSame($before, DB::table('stock_balances')->value('on_hand'));
        $this->staff('shipments/'.$f['id'].'/retry', ['confirmed' => true])->assertUnprocessable();
    }

    public function test_dispatched_shipment_cannot_be_cancelled_and_repeated_dispatch_queues_once(): void
    {
        $f = $this->book();
        $this->staff('shipments/'.$f['id'].'/dispatch', ['confirmed' => true])->assertOk();
        $this->staff('shipments/'.$f['id'].'/dispatch', ['confirmed' => true])->assertOk();
        $this->staff('shipments/'.$f['id'].'/cancel', ['confirmed' => true])->assertUnprocessable();
        $this->assertDatabaseCount('order_notifications', 1);
    }

    public function test_quote_requires_valid_parcel_active_committed_origin_and_confirmed_review(): void
    {
        $v = $this->parcel();
        $v['parcel']['weight_grams'] = 0;
        $this->staff('orders/'.$this->order['id'].'/shipment-quotes', $v)->assertUnprocessable();
        $other = DB::table('stock_locations')->insertGetId(['name' => 'Other location', 'code' => 'OTHER', 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $v = $this->parcel();
        $v['location_id'] = $other;
        $this->staff('orders/'.$this->order['id'].'/shipment-quotes', $v)->assertUnprocessable();
        $q = $this->quote();
        $this->staff('orders/'.$this->order['id'].'/shipments', ['quote_id' => $q['id'], 'confirmed' => false])->assertUnprocessable();
        $this->travel(11)->minutes();
        $this->staff('orders/'.$this->order['id'].'/shipments', ['quote_id' => $q['id'], 'confirmed' => true])->assertUnprocessable();
        $this->assertDatabaseCount('order_fulfilments', 0);
    }

    public function test_tracking_request_is_neutral_for_wrong_email_or_missing_reference_and_has_no_tokens(): void
    {
        $wrong = $this->guest('request', ['reference' => $this->order['reference'], 'email' => 'other@example.test'])->assertOk()->json('data');
        $missing = $this->guest('request', ['reference' => 'EV-NO-ORDER', 'email' => 'sandbox-guest@example.test'])->assertOk()->json('data');
        $this->assertSame($wrong, $missing);
        $this->assertDatabaseCount('order_access_links', 0);
        $good = $this->guest('request', ['reference' => $this->order['reference'], 'email' => 'sandbox-guest@example.test'])->assertOk()->json('data');
        $this->assertSame($wrong, $good);
        $this->assertDatabaseCount('order_access_links', 1);
        $this->guest('order')->assertUnauthorized();
    }

    public function test_tracking_one_use_link_exchanges_for_scoped_session_without_addresses_notes_or_labels(): void
    {
        $one = $this->book();
        $token = $this->trackingToken();
        $grant = $this->guest('redeem', ['token' => $token])->assertOk()->json('data.token');
        $this->guest('redeem', ['token' => $token])->assertUnauthorized();
        $view = $this->guest('order', null, $grant)->assertOk()->assertJsonPath('data.reference', $this->order['reference'])->json('data');
        $json = json_encode($view);
        foreach (['1 Test Street', 'sandbox-guest@example.test', 'billing_address', 'shipping_address', 'label', 'notes', 'attempts'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }$this->assertCount(1, $view['shipments']);
        $this->assertFalse(DB::table('order_access_links')->where('token_hash', $token)->exists());
        $other = $this->paidOrder(1);
        $this->guest('order?order_id='.$other['id'], null, $grant)->assertJsonPath('data.reference', $this->order['reference']);
        $this->guest('logout', [], $grant)->assertOk();
        $this->guest('order', null, $grant)->assertUnauthorized();
    }

    public function test_expired_link_or_tracking_session_cannot_be_reused(): void
    {
        $token = $this->trackingToken();
        $this->travel(31)->minutes();
        $this->guest('redeem', ['token' => $token])->assertUnauthorized();
        $this->travelBack();
        $token = $this->trackingToken();
        $grant = $this->guest('redeem', ['token' => $token])->assertOk()->json('data.token');
        $this->travel(25)->hours();
        $this->guest('order', null, $grant)->assertUnauthorized();
    }

    public function test_verified_support_request_is_scoped_idempotent_and_visible_only_to_staff(): void
    {
        $this->guest('support', ['operation_id' => (string) Str::uuid(), 'subject' => 'Where is it', 'message' => 'Please check delivery'])->assertUnauthorized();
        $token = $this->trackingToken();
        $grant = $this->guest('redeem', ['token' => $token])->json('data.token');
        $v = ['operation_id' => (string) Str::uuid(), 'subject' => 'Delivery question', 'message' => 'Please confirm the parcel progress'];
        $this->guest('support', $v, $grant)->assertOk();
        $this->guest('support', $v, $grant)->assertOk();
        $this->assertDatabaseCount('order_support_requests', 1);
        $this->staff('orders/'.$this->order['id'])->assertJsonPath('data.support_requests.0.message', $v['message']);
        $this->guest('order', null, $grant)->assertJsonMissing(['message' => $v['message']]);
    }

    public function test_signed_tracking_events_deduplicate_ignore_regressions_and_preserve_payment_state(): void
    {
        $f = $this->book(2);
        $when = now()->toISOString();
        $this->event($f, 'in_transit', when: $when)->assertOk();
        $this->event($f, 'in_transit', when: $when)->assertOk();
        $this->event($f, 'delivered', 'scan-delivered', now()->addMinute()->toISOString())->assertOk();
        $this->event($f, 'out_for_delivery', 'scan-late', now()->toISOString())->assertOk();
        $this->event($f, 'exception', 'scan-exception', now()->addMinutes(2)->toISOString())->assertOk();
        $this->assertDatabaseHas('order_fulfilments', ['id' => $f['id'], 'state' => 'delivered']);
        $this->assertDatabaseHas('commerce_orders', ['id' => $this->order['id'], 'fulfilment_state' => 'delivered', 'payment_state' => 'paid', 'order_state' => 'confirmed']);
        $this->assertDatabaseCount('courier_tracking_events', 4);
        $this->assertSame(2, DB::table('courier_tracking_events')->where('applied', true)->count());
        $this->assertSame(1, DB::table('order_notifications')->where('kind', 'dispatch')->count());
    }

    public function test_unsigned_bad_signature_unknown_identity_and_disabled_webhooks_are_denied(): void
    {
        $f = $this->book();
        $this->event($f, 'in_transit', valid: false)->assertStatus(400);
        $wrong = $f;
        $wrong['tracking_number'] = 'OTHER';
        $this->event($wrong, 'in_transit')->assertNotFound();
        config(['fulfilment.webhook_secret' => '']);
        $this->event($f, 'in_transit')->assertStatus(503);
        $this->assertDatabaseCount('courier_tracking_events', 0);
    }

    public function test_late_courier_events_for_cancelled_shipment_are_recorded_but_do_not_restore_it(): void
    {
        $f = $this->book();
        $this->staff('shipments/'.$f['id'].'/cancel', ['confirmed' => true])->assertOk();
        $this->event($f, 'delivered')->assertOk();
        $this->assertDatabaseHas('courier_tracking_events', ['id' => 'scan-1', 'applied' => 0]);
        $this->assertDatabaseHas('order_fulfilments', ['id' => $f['id'], 'state' => 'cancelled']);
        $this->assertDatabaseHas('commerce_orders', ['id' => $this->order['id'], 'fulfilment_state' => 'unfulfilled']);
    }

    public function test_notification_worker_is_deterministic_private_and_hides_links_from_staff_details(): void
    {
        $this->trackingToken();
        $n = DB::table('order_notifications')->first();
        $this->assertStringNotContainsString('token=', $n->encrypted_payload);
        (new DeliverOrderNotification($n->id))->handle();
        (new DeliverOrderNotification($n->id))->handle();
        $files = Storage::disk('local')->allFiles('order-notifications');
        $this->assertCount(1, $files);
        $this->assertStringContainsString('/track#token=', Storage::disk('local')->get($files[0]));
        $detail = $this->staff('orders/'.$this->order['id'])->assertOk()->json('data');
        $this->assertStringNotContainsString('token=', json_encode($detail));
        $this->assertNotNull($detail['notifications'][0]['delivered_at']);
    }

    public function test_manual_invitation_redemption_and_local_expiry_reuse_checkout_stock_policy(): void
    {
        config(['commerce.enabled' => false]);
        $v = F::details(1) + ['items' => [['variant_id' => $this->variant, 'quantity' => 1]]];
        unset($v['revision']);
        $q = $this->staff('orders/manual/quote', $v)->json('data');
        $o = $this->staff('orders/manual/create', $v + ['quote_hash' => $q['quote_hash'], 'operation_id' => (string) Str::uuid(), 'confirmed' => true])->assertOk()->json('data');
        $n = DB::table('order_notifications')->where('kind', 'payment_link')->first();
        $payload = json_decode(Crypt::decryptString($n->encrypted_payload), true);
        parse_str(parse_url($payload['url'], PHP_URL_FRAGMENT), $fragment);
        $grant = $this->guest('payment-redeem', ['token' => $fragment['token']])->assertOk()->json('data');
        $this->guest('payment-redeem', ['token' => $fragment['token']])->assertUnauthorized();
        $this->assertDatabaseHas('commerce_orders', ['id' => $o['id'], 'payment_state' => 'creating']);
        $this->assertDatabaseHas('commerce_bags', ['id' => DB::table('commerce_orders')->find($o['id'])->bag_id, 'token_hash' => hash('sha256', $grant['token'])]);
        $this->artisan('commerce:reconcile')->assertSuccessful();
        $this->assertDatabaseHas('commerce_payments', ['order_id' => $o['id'], 'creation_started_at' => null]);
        $this->travel(2)->hours();
        $this->artisan('orders:maintenance')->assertSuccessful();
        $this->assertDatabaseHas('commerce_orders', ['id' => $o['id'], 'payment_state' => 'expired']);
        $this->assertDatabaseHas('stock_balances', ['reserved' => 0]);
    }

    public function test_disabled_payment_refresh_never_marks_manual_creation_attempted_or_leaks_bag_token(): void
    {
        config(['commerce.enabled' => false]);
        $v = F::details(1) + ['items' => [['variant_id' => $this->variant, 'quantity' => 1]]];
        $q = $this->staff('orders/manual/quote', $v)->assertOk()->json('data');
        $o = $this->staff('orders/manual/create', $v + ['operation_id' => (string) Str::uuid(), 'quote_hash' => $q['quote_hash'], 'confirmed' => true])->assertOk()->json('data');
        app(Checkout::class)->ensureSession($o['id']);
        $this->assertDatabaseHas('commerce_payments', ['order_id' => $o['id'], 'creation_started_at' => null, 'session_id' => null]);
        $this->travel(2)->hours();
        app(Checkout::class)->expireUnattemptedManual($o['id']);
        $this->assertDatabaseHas('commerce_orders', ['id' => $o['id'], 'payment_state' => 'expired']);
        $this->assertDatabaseHas('stock_balances', ['reserved' => 0]);
    }

    public function test_event_id_collision_or_stale_signature_cannot_mutate_tracking(): void
    {
        $f = $this->book();
        $when = now()->toISOString();
        $this->event($f, 'in_transit', when: $when)->assertOk();
        $this->event($f, 'delivered', when: $when)->assertStatus(409);
        $body = '{}';
        $t = time() - 301;
        $sig = hash_hmac('sha256', $t.'.'.$body, config('fulfilment.webhook_secret'));
        $this->call('POST', '/api/v1/courier/sandbox/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SANDBOX_SIGNATURE' => 't='.$t.',v1='.$sig], $body)->assertStatus(400);
        $this->assertDatabaseCount('courier_tracking_events', 1);
        $this->assertDatabaseHas('order_fulfilments', ['id' => $f['id'], 'state' => 'in_transit']);
    }

    public function test_foreign_order_items_and_cross_order_quote_ids_are_rejected(): void
    {
        $other = $this->paidOrder(1);
        $q = $this->quote();
        $v = $this->parcel(1, $other);
        $this->staff('orders/'.$this->order['id'].'/shipment-quotes', $v)->assertUnprocessable();
        $this->staff('orders/'.$other['id'].'/shipments', ['quote_id' => $q['id'], 'confirmed' => true])->assertNotFound();
        $this->assertDatabaseCount('order_fulfilments', 0);
    }

    public function test_prepared_parcel_rejects_altered_eligibility_and_late_booking_keeps_same_cost(): void
    {
        $q = $this->quote();
        $f = app(Shipments::class)->prepare($this->order['id'], $q['id'], $this->staff->id);
        config(['fulfilment.services' => ['sandbox_standard' => ['name' => 'Changed config', 'cost_minor' => 9999]]]);
        $this->travel(11)->minutes();
        $booked = app(Shipments::class)->book($f->id, $this->staff->id);
        $this->assertSame(395, $booked['cost_minor']);
        $this->assertSame('booked', $booked['state']);
        $this->assertDatabaseCount('sandbox_courier_receipts', 1);
    }

    public function test_tracking_read_budget_does_not_consume_support_or_link_request_limits(): void
    {
        $token = $this->trackingToken();
        $grant = $this->guest('redeem', ['token' => $token])->assertOk()->json('data.token');
        for ($i = 0; $i < 15; $i++) {
            $this->guest('order', null, $grant)->assertOk();
        }
        $this->guest('support', ['operation_id' => (string) Str::uuid(), 'subject' => 'Rate isolation', 'message' => 'Reads must not consume this separate support budget.'], $grant)->assertOk();
        $this->guest('request', ['reference' => $this->order['reference'], 'email' => 'sandbox-guest@example.test'])->assertOk();
    }
}
