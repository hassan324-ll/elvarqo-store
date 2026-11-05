<?php

namespace Tests\Feature;

use App\Commerce\Bags;
use App\Commerce\Checkout;
use App\Commerce\Pricing;
use App\Jobs\DeliverSandboxOrderMail;
use App\Models\CatalogueVariant;
use App\Models\StaffRole;
use App\Models\StaffUser;
use App\Models\StockLocation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CommerceFixture as F;
use Tests\TestCase;

class CommerceTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private int $variant;

    private ?array $provider = null;

    private array $requests = [];

    private bool $providerDown = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['commerce.enabled' => true, 'commerce.secret' => 'sk_test_contract_only', 'commerce.webhook_secret' => 'whsec_contract_only']);
        Http::preventStrayRequests();
        $this->variant = F::seed();
        $this->token = app(Bags::class)->create()['token'];
        Http::fake(function ($request) {
            if ($this->providerDown) {
                return Http::response([], 503);
            }$this->requests[] = $request;
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/checkout/sessions')) {
                $this->provider = F::session($request->data());

                return Http::response($this->provider);
            }if (str_ends_with($request->url(), '/expire')) {
                $this->provider['status'] = 'expired';

                return Http::response($this->provider);
            }

            return Http::response($this->provider);
        });
    }

    private function send(string $method, string $path, array $data = [], ?string $token = null)
    {
        return $this->json($method, '/api/v1/commerce/'.$path, $data, ['Origin' => config('commerce.storefront_origin'), 'Authorization' => 'Bearer '.($token ?? $this->token), 'Accept' => 'application/json']);
    }

    private function bag(): array
    {
        return $this->send('GET', 'bag')->assertOk()->json('data');
    }

    private function add(int $qty = 1): void
    {
        $this->send('POST', 'bag/items', ['variant_id' => $this->variant, 'quantity' => $qty, 'revision' => $this->bag()['revision']])->assertOk();
    }

    private function input(): array
    {
        $v = F::details($this->bag()['revision']);
        $q = $this->send('POST', 'quote', $v)->assertOk()->json('data');

        return $v + ['quote_hash' => $q['quote_hash'], 'attempt_id' => (string) Str::uuid(), 'terms' => true];
    }

    private function start(): array
    {
        $this->add();

        return $this->send('POST', 'checkout', $this->input())->assertOk()->json('data');
    }

    private function event(string $type = 'checkout.session.completed', string $id = 'evt_contract_1', bool $valid = true)
    {
        $event = ['id' => $id, 'type' => $type, 'livemode' => false, 'data' => ['object' => $this->provider]];
        $body = json_encode($event);
        $time = time();
        $signature = hash_hmac('sha256', $time.'.'.$body, config('commerce.webhook_secret'));

        return $this->call('POST', '/api/v1/payments/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.$time.',v1='.($valid ? $signature : 'bad')], $body);
    }

    public function test_bag_capability_ownership_origin_revision_and_private_caching(): void
    {
        $this->getJson('/api/v1/commerce/bag')->assertUnauthorized();
        $this->postJson('/api/v1/commerce/bags', [])->assertForbidden();
        $this->add();
        $this->send('GET', 'bag')->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('data.items.0.unit_minor', 6800);
        $this->send('POST', 'bag/items', ['variant_id' => $this->variant, 'quantity' => 2, 'revision' => 1])->assertUnprocessable();
        $this->send('POST', 'bag/items', ['variant_id' => $this->variant, 'quantity' => 0, 'revision' => 2])->assertOk()->assertJsonCount(0, 'data.items');
        $this->assertFalse(DB::table('commerce_bags')->where('token_hash', $this->token)->exists());
    }

    public function test_always_available_can_checkout_and_settle_without_stock_balances(): void
    {
        config(['catalogue.track_stock' => false]);
        DB::table('stock_balances')->delete();
        $o = $this->start();
        $this->assertFalse(app(Checkout::class)->snapshot($o['id'])['items'][0]['stock_tracked']);
        $this->assertDatabaseCount('commerce_reservations', 0);
        $this->assertDatabaseCount('commerce_stock_events', 0);
        $this->provider['payment_status'] = 'paid';
        $this->provider['status'] = 'complete';
        $this->event()->assertOk();
        $this->assertDatabaseHas('commerce_orders', ['id' => $o['id'], 'payment_state' => 'paid']);
        $this->event()->assertOk();
        $this->assertDatabaseCount('stock_balances', 0);
    }

    public function test_always_available_expiry_requires_no_stock_and_inactive_variants_stay_blocked(): void
    {
        config(['catalogue.track_stock' => false]);
        DB::table('stock_balances')->update(['on_hand' => 0]);
        $o = $this->start();
        $this->provider['status'] = 'expired';
        $this->event('checkout.session.expired')->assertOk();
        $this->assertDatabaseHas('commerce_orders', ['id' => $o['id'], 'payment_state' => 'expired']);
        CatalogueVariant::findOrFail($this->variant)->update(['active' => false]);
        $this->assertTrue(app(Pricing::class)->lines([$this->variant => 1])[0]['unavailable']);
    }

    public function test_switching_modes_retains_existing_order_reservations_and_does_not_invent_stock(): void
    {
        $o = $this->start();
        $this->assertDatabaseCount('commerce_reservations', 1);
        config(['catalogue.track_stock' => false]);
        $this->provider['payment_status'] = 'paid';
        $this->provider['status'] = 'complete';
        $this->event()->assertOk();
        $this->assertSame(4, (int) DB::table('stock_balances')->sum('on_hand'));
        $this->assertSame(0, (int) DB::table('stock_balances')->sum('reserved'));
        config(['catalogue.track_stock' => true]);
        DB::table('stock_balances')->update(['on_hand' => 0]);
        $this->assertTrue(app(Pricing::class)->lines([$this->variant => 1])[0]['unavailable']);
    }

    public function test_stock_mode_is_server_controlled_and_changes_invalidate_a_quote(): void
    {
        config(['catalogue.track_stock' => false]);
        $this->add();
        $v = $this->input();
        config(['catalogue.track_stock' => true]);
        $this->send('POST', 'checkout', $v + ['stock_tracked' => false])->assertUnprocessable();
        $this->assertDatabaseCount('commerce_orders', 0);
    }

    public function test_server_ignores_browser_prices_and_rounds_test_discount_totals(): void
    {
        $this->add(2);
        $v = F::details($this->bag()['revision']) + ['total_minor' => 1, 'unit_minor' => 1, 'tax_minor' => -999, 'shipping_minor' => 0];
        $this->send('POST', 'quote', $v)->assertOk()->assertJsonPath('data.snapshot.subtotal_minor', 13600)->assertJsonPath('data.snapshot.discount_minor', 1360)->assertJsonPath('data.snapshot.shipping_minor', 495)->assertJsonPath('data.snapshot.tax_minor', 0)->assertJsonPath('data.snapshot.total_minor', 12735);
    }

    public function test_invalid_addresses_quantities_codes_delivery_and_terms_are_rejected(): void
    {
        foreach ([0, 11, -1] as $quantity) {
            if ($quantity !== 0) {
                $this->send('POST', 'bag/items', ['variant_id' => $this->variant, 'quantity' => $quantity, 'revision' => 1])->assertUnprocessable();
            }
        }
        $this->add();
        $v = F::details(2);
        foreach ([['delivery_method' => 'free'], ['discount_code' => 'WELCOME10'], ['email' => 'invalid'], ['address' => array_replace($v['address'], ['country' => 'US'])], ['address' => array_replace($v['address'], ['postcode' => 'invalid'])]] as $change) {
            $this->send('POST', 'quote', array_replace($v, $change))->assertUnprocessable();
        }
        $in = $this->input();
        $in['terms'] = false;
        $this->send('POST', 'checkout', $in)->assertUnprocessable();
        $this->assertDatabaseCount('commerce_orders', 0);
    }

    public function test_price_changes_and_unpublishing_invalidate_quotes_before_any_reservation(): void
    {
        $this->add();
        $input = $this->input();
        CatalogueVariant::where('id', $this->variant)->update(['price_minor' => 7100]);
        $this->send('POST', 'checkout', $input)->assertUnprocessable();
        $this->assertDatabaseCount('commerce_orders', 0);
        $this->assertDatabaseHas('stock_balances', ['reserved' => 0]);
        DB::table('catalogue_products')->update(['status' => 'draft']);
        $this->bag();
        $this->send('POST', 'quote', F::details(2))->assertUnprocessable();
    }

    public function test_checkout_replay_reserves_once_and_returns_one_order_and_one_payment(): void
    {
        $this->add();
        $input = $this->input();
        $one = $this->send('POST', 'checkout', $input)->assertOk()->json('data');
        $this->send('POST', 'checkout', $input)->assertOk()->assertJsonPath('data.id', $one['id']);
        $this->assertDatabaseCount('commerce_orders', 1);
        $this->assertDatabaseCount('commerce_order_items', 1);
        $this->assertDatabaseCount('commerce_order_addresses', 2);
        $this->assertDatabaseCount('commerce_payments', 1);
        $this->assertDatabaseCount('commerce_stock_events', 1);
        $this->assertDatabaseHas('stock_balances', ['on_hand' => 5, 'reserved' => 1]);
        $this->assertCount(1, $this->requests);
        $this->assertSame('elvarqo-checkout-'.$input['attempt_id'], $this->requests[0]->header('Idempotency-Key')[0]);
        $changed = $input;
        $changed['email'] = 'changed@example.test';
        $this->send('POST', 'checkout', $changed)->assertUnprocessable();
        $changed = $input;
        $changed['attempt_id'] = (string) Str::uuid();
        $this->send('POST', 'checkout', $changed)->assertUnprocessable();
    }

    public function test_redirect_and_browser_status_cannot_mark_paid_and_other_guests_cannot_read_order(): void
    {
        $o = $this->start();
        $this->send('GET', 'orders/'.$o['id'].'?paid=true')->assertJsonPath('data.payment_state', 'open');
        $other = app(Bags::class)->create()['token'];
        $this->send('GET', 'orders/'.$o['id'], [], $other)->assertNotFound();
        $this->assertDatabaseCount('commerce_mail_outbox', 0);
    }

    public function test_verified_paid_event_commits_stock_once_and_deduplicates_events_and_outbox(): void
    {
        $o = $this->start();
        $this->provider['status'] = 'complete';
        $this->provider['payment_status'] = 'paid';
        $this->event()->assertOk();
        $this->event()->assertOk();
        $this->event('checkout.session.expired', 'evt_late')->assertOk();
        $this->send('GET', 'orders/'.$o['id'])->assertJsonPath('data.payment_state', 'paid')->assertJsonPath('data.order_state', 'confirmed');
        $this->assertDatabaseHas('stock_balances', ['on_hand' => 4, 'reserved' => 0]);
        $this->assertDatabaseCount('commerce_orders', 1);
        $this->assertDatabaseCount('commerce_mail_outbox', 1);
        $this->assertDatabaseCount('commerce_stock_events', 2);
        $this->assertDatabaseCount('commerce_webhook_events', 2);
        $this->assertSame([], $this->bag()['items']);
    }

    public function test_invalid_or_stale_signatures_and_mode_are_rejected_without_side_effects(): void
    {
        $this->start();
        $this->event(valid: false)->assertStatus(400);
        $body = '{}';
        $time = time() - 301;
        $sig = hash_hmac('sha256', $time.'.'.$body, config('commerce.webhook_secret'));
        $this->call('POST', '/api/v1/payments/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.$time.',v1='.$sig], $body)->assertStatus(400);
        $this->assertDatabaseCount('commerce_webhook_events', 0);
        $this->assertDatabaseHas('stock_balances', ['on_hand' => 5, 'reserved' => 1]);
    }

    public function test_provider_amount_mismatch_holds_stock_for_review_without_confirmation(): void
    {
        $o = $this->start();
        $this->provider['status'] = 'complete';
        $this->provider['payment_status'] = 'paid';
        $this->provider['amount_total'] = 1;
        $this->event()->assertStatus(500);
        $this->assertDatabaseHas('commerce_orders', ['id' => $o['id'], 'payment_state' => 'review', 'fulfilment_state' => 'on_hold']);
        $this->assertDatabaseHas('stock_balances', ['on_hand' => 5, 'reserved' => 1]);
        $this->assertDatabaseCount('commerce_mail_outbox', 0);
    }

    public function test_expiry_releases_stock_once_preserves_bag_and_allows_new_attempt(): void
    {
        $o = $this->start();
        $this->provider['status'] = 'expired';
        $this->event('checkout.session.expired')->assertOk();
        $this->event('checkout.session.expired', 'evt_expire_again')->assertOk();
        $this->assertDatabaseHas('stock_balances', ['on_hand' => 5, 'reserved' => 0]);
        $this->assertCount(1, $this->bag()['items']);
        $this->assertNull($this->bag()['active_order_id']);
        $this->assertDatabaseCount('commerce_stock_events', 2);
        $this->send('POST', 'checkout', $this->input())->assertOk();
        $this->assertDatabaseCount('commerce_orders', 2);
    }

    public function test_unpaid_complete_is_pending_and_does_not_release_even_after_local_deadline(): void
    {
        $o = $this->start();
        $this->provider['status'] = 'complete';
        $this->event()->assertOk();
        $this->send('GET', 'orders/'.$o['id'])->assertJsonPath('data.payment_state', 'pending');
        $this->travel(2)->hours();
        $this->send('POST', 'orders/'.$o['id'].'/resume', [])->assertOk()->assertJsonPath('data.payment_state', 'pending');
        $this->assertDatabaseHas('stock_balances', ['reserved' => 1]);
        $this->assertDatabaseCount('commerce_mail_outbox', 0);
    }

    public function test_provider_timeout_retains_reservations_and_retry_reuses_order_and_request_key(): void
    {
        $this->providerDown = true;
        $this->add();
        $input = $this->input();
        $o = $this->send('POST', 'checkout', $input)->assertOk()->assertJsonPath('data.payment_state', 'creating')->json('data');
        $this->assertDatabaseHas('stock_balances', ['reserved' => 1]);
        $this->providerDown = false;
        $this->send('POST', 'checkout', $input)->assertOk()->assertJsonPath('data.id', $o['id'])->assertJsonPath('data.payment_state', 'open');
        $this->assertDatabaseCount('commerce_orders', 1);
        $this->assertDatabaseCount('commerce_reservations', 1);
    }

    public function test_unknown_provider_status_does_not_release_expired_local_reservations(): void
    {
        $o = $this->start();
        $this->travel(2)->hours();
        $this->providerDown = true;
        $this->send('POST', 'orders/'.$o['id'].'/resume', [])->assertOk()->assertJsonPath('data.refresh_unavailable', true);
        $this->assertDatabaseHas('stock_balances', ['on_hand' => 5, 'reserved' => 1]);
    }

    public function test_reconciliation_recovers_missing_webhook_and_queues_once(): void
    {
        $o = $this->start();
        $this->provider['status'] = 'complete';
        $this->provider['payment_status'] = 'paid';
        $this->artisan('commerce:reconcile')->assertSuccessful();
        $this->artisan('commerce:reconcile')->assertSuccessful();
        $this->assertDatabaseHas('commerce_orders', ['id' => $o['id'], 'payment_state' => 'paid']);
        $this->assertDatabaseCount('commerce_mail_outbox', 1);
    }

    public function test_private_mail_outbox_job_is_replay_safe_and_never_sends_external_mail(): void
    {
        Storage::fake('local');
        $o = $this->start();
        $this->provider['status'] = 'complete';
        $this->provider['payment_status'] = 'paid';
        $this->event()->assertOk();
        (new DeliverSandboxOrderMail($o['id']))->handle();
        (new DeliverSandboxOrderMail($o['id']))->handle();
        $files = Storage::disk('local')->allFiles('commerce-mail');
        $this->assertCount(1, $files);
        $this->assertStringContainsString('GBP 66.15', Storage::disk('local')->get($files[0]));
        $this->assertNotNull(DB::table('commerce_mail_outbox')->value('delivered_at'));
    }

    public function test_staff_order_views_require_mfa_session_and_fulfilment_permission(): void
    {
        $o = $this->start();
        $this->getJson('/api/v1/staff/orders')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer '.$this->token)->getJson('/api/v1/staff/orders/'.$o['id'])->assertUnauthorized();
        $staff = StaffUser::create(['name' => 'QA Reader', 'email' => 'qa-reader@example.test', 'password' => 'TestOnly-Staff-Password-42!', 'staff_role_id' => StaffRole::where('name', 'support')->value('id'), 'two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()]);
        $staff->refresh();
        $this->actingAs($staff, 'staff')->withSession(['staff_version' => $staff->auth_version, 'staff_authenticated_at' => now()->timestamp, 'staff_seen_at' => now()->timestamp]);
        $this->getJson('/api/v1/staff/orders')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/v1/staff/orders/'.$o['id'])->assertOk()->assertJsonPath('data.snapshot.items.0.unit_minor', 6800);
        $staff->staff_role_id = StaffRole::where('name', 'finance')->value('id');
        $staff->save();
        $staff->unsetRelation('role');
        $this->getJson('/api/v1/staff/orders')->assertForbidden();
        $this->postJson('/api/v1/staff/orders/'.$o['id'], [])->assertStatus(405);
    }

    public function test_disabled_or_live_keys_fail_closed_before_creating_an_order(): void
    {
        $this->add();
        $input = $this->input();
        foreach ([['commerce.enabled' => false], ['commerce.enabled' => true, 'commerce.secret' => 'sk_live_forbidden']] as $c) {
            config($c);
            $this->send('POST', 'checkout', $input)->assertStatus(503);
        }$this->assertDatabaseCount('commerce_orders', 0);
        $this->assertDatabaseHas('stock_balances', ['reserved' => 0]);
    }

    public function test_snapshot_and_automated_stock_ledger_cannot_be_rewritten(): void
    {
        $o = $this->start();
        $before = app(Checkout::class)->snapshot($o['id']);
        CatalogueVariant::where('id', $this->variant)->update(['price_minor' => 9999]);
        $this->assertSame($before, app(Checkout::class)->snapshot($o['id']));
        $this->expectException(QueryException::class);
        DB::table('commerce_order_snapshots')->where('order_id', $o['id'])->update(['payload' => '{}']);
    }

    public function test_retried_provider_request_is_frozen_even_when_configuration_changes(): void
    {
        $this->providerDown = true;
        $this->add();
        $input = $this->input();
        $o = $this->send('POST', 'checkout', $input)->assertOk()->json('data');
        $before = DB::table('commerce_payments')->value('request_payload');
        config(['commerce.storefront_origin' => 'http://localhost:3999', 'commerce.session_seconds' => 7200]);
        $this->providerDown = false;
        $this->send('POST', 'checkout', $input)->assertOk()->assertJsonPath('data.id', $o['id']);
        $sent = end($this->requests)->data();
        $this->assertSame(json_decode($before, true), $sent);
        $this->assertStringStartsWith('http://localhost:3000/', $sent['success_url']);
    }

    public function test_scheduler_expires_verified_open_session_and_releases_only_once(): void
    {
        $this->start();
        $this->travel(2)->hours();
        $this->artisan('commerce:reconcile')->assertSuccessful();
        $this->assertDatabaseHas('stock_balances', ['on_hand' => 5, 'reserved' => 0]);
        $this->assertDatabaseHas('commerce_orders', ['payment_state' => 'expired']);
        $this->artisan('commerce:reconcile')->assertSuccessful();
        $this->assertDatabaseCount('commerce_stock_events', 2);
    }

    public function test_verified_async_failure_releases_stock_but_late_success_requires_review(): void
    {
        $o = $this->start();
        $this->provider['status'] = 'complete';
        $this->provider['payment_status'] = 'unpaid';
        $this->event('checkout.session.async_payment_failed')->assertOk();
        $this->assertDatabaseHas('commerce_orders', ['id' => $o['id'], 'payment_state' => 'failed']);
        $this->assertDatabaseHas('stock_balances', ['on_hand' => 5, 'reserved' => 0]);
        $this->assertCount(1, $this->bag()['items']);
        $this->provider['payment_status'] = 'paid';
        $this->event('checkout.session.async_payment_succeeded', 'evt_late_success')->assertOk();
        $this->assertDatabaseHas('commerce_orders', ['id' => $o['id'], 'payment_state' => 'review', 'fulfilment_state' => 'on_hold']);
        $this->assertDatabaseHas('stock_balances', ['on_hand' => 5, 'reserved' => 0]);
        $this->assertDatabaseCount('commerce_mail_outbox', 0);
    }

    public function test_multi_location_reservations_commit_exact_quantities_and_keep_existing_reserved_stock(): void
    {
        DB::table('stock_balances')->update(['on_hand' => 2, 'reserved' => 1]);
        $location = StockLocation::create(['name' => 'Second QA warehouse', 'code' => 'SECOND']);
        DB::table('stock_balances')->insert(['variant_id' => $this->variant, 'location_id' => $location->id, 'on_hand' => 4, 'reserved' => 1]);
        $this->add(3);
        $o = $this->send('POST', 'checkout', $this->input())->assertOk()->json('data');
        $this->assertDatabaseCount('commerce_reservations', 2);
        $this->assertSame(5, (int) DB::table('stock_balances')->sum('reserved'));
        $this->provider['status'] = 'complete';
        $this->provider['payment_status'] = 'paid';
        $this->event()->assertOk();
        $this->assertSame(3, (int) DB::table('stock_balances')->sum('on_hand'));
        $this->assertSame(2, (int) DB::table('stock_balances')->sum('reserved'));
        $this->assertDatabaseCount('commerce_stock_events', 4);
    }

    public function test_stock_change_after_review_rolls_back_the_entire_checkout(): void
    {
        $this->add(2);
        $input = $this->input();
        DB::table('stock_balances')->update(['on_hand' => 1]);
        $this->send('POST', 'checkout', $input)->assertUnprocessable();
        foreach (['commerce_orders', 'commerce_order_items', 'commerce_order_addresses', 'commerce_order_snapshots', 'commerce_checkout_attempts', 'commerce_payments', 'commerce_reservations', 'commerce_stock_events'] as $t) {
            $this->assertDatabaseCount($t, 0);
        }$this->assertDatabaseHas('stock_balances', ['reserved' => 0]);
    }

    public function test_ambiguous_creation_stops_before_provider_idempotency_retention_expires(): void
    {
        $this->providerDown = true;
        $o = $this->start();
        $this->travel(24)->hours();
        $this->providerDown = false;
        $this->send('POST', 'orders/'.$o['id'].'/resume', [])->assertOk()->assertJsonPath('data.payment_state', 'review');
        $this->assertCount(0, $this->requests);
        $this->assertDatabaseHas('stock_balances', ['reserved' => 1]);
        $this->assertDatabaseCount('commerce_mail_outbox', 0);
    }

    #[DataProvider('invalidEvidence')]
    public function test_mismatched_provider_identity_mode_or_currency_never_confirms(string $field, mixed $value): void
    {
        $o = $this->start();
        $this->provider['status'] = 'complete';
        $this->provider['payment_status'] = 'paid';
        $this->provider[$field] = $value;
        $this->send('POST', 'orders/'.$o['id'].'/resume', [])->assertOk()->assertJsonPath('data.payment_state', 'review');
        $this->assertDatabaseHas('stock_balances', ['on_hand' => 5, 'reserved' => 1]);
        $this->assertDatabaseCount('commerce_mail_outbox', 0);
    }

    public static function invalidEvidence(): array
    {
        return [['currency', 'usd'], ['client_reference_id', 'wrong-order'], ['livemode', true], ['mode', 'subscription'], ['id', 'cs_test_unrelated']];
    }

    public function test_valid_numeric_strings_are_normalized_and_zero_quantity_removes_a_line(): void
    {
        $this->send('POST', 'bag/items', ['variant_id' => (string) $this->variant, 'quantity' => '2', 'revision' => '1'])->assertOk()->assertJsonPath('data.items.0.quantity', 2);
        $this->send('POST', 'quote', array_replace(F::details(), ['revision' => '2']))->assertOk()->assertJsonPath('data.snapshot.items.0.quantity', 2);
        $this->send('POST', 'bag/items', ['variant_id' => (string) $this->variant, 'quantity' => '0', 'revision' => '2'])->assertOk()->assertJsonCount(0, 'data.items');
    }
}
