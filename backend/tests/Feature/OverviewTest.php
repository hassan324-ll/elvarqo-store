<?php

namespace Tests\Feature;

use App\Models\CatalogueProduct;
use App\Models\StaffRole;
use App\Models\StaffUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OverviewTest extends TestCase
{
    use RefreshDatabase;

    private function loginWith(array $permissions = ['catalogue.view', 'fulfilment.view', 'finance.view']): void
    {
        $role = StaffRole::create(['name' => (string) Str::uuid(), 'permissions' => $permissions]);
        $staff = StaffUser::create(['name' => 'Overview Test', 'email' => Str::uuid().'@example.test', 'password' => 'Test-only-password-42!', 'staff_role_id' => $role->id, 'two_factor_confirmed_at' => now()]);
        $staff->refresh();
        $this->actingAs($staff, 'staff')->withSession(['staff_version' => $staff->auth_version, 'staff_authenticated_at' => now()->timestamp, 'staff_seen_at' => now()->timestamp]);
    }

    private function overview()
    {
        return $this->getJson('/api/v1/staff/overview', ['Origin' => config('staff.admin_origin')]);
    }

    public function test_anonymous_access_is_rejected(): void
    {
        $this->overview()->assertUnauthorized();
    }

    public function test_empty_store_shows_real_zeroes_and_empty_lists(): void
    {
        $this->loginWith();
        $this->overview()->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.catalogue.products', 0)->assertJsonPath('data.catalogue.available_units', 0)
            ->assertJsonPath('data.catalogue.recent', [])->assertJsonPath('data.orders.total', 0)
            ->assertJsonPath('data.orders.recent', [])->assertJsonPath('data.paid_totals', []);
    }

    public function test_product_creation_publication_and_archival_change_totals(): void
    {
        $this->loginWith();
        $p = CatalogueProduct::create(['title' => 'Real draft', 'slug' => 'real-draft']);
        $this->overview()->assertOk()->assertJsonPath('data.catalogue.products', 1)
            ->assertJsonPath('data.catalogue.drafts', 1)->assertJsonPath('data.catalogue.published', 0)
            ->assertJsonPath('data.catalogue.recent.0.title', 'Real draft')->assertJsonPath('data.orders.total', 0);
        $p->update(['status' => 'published', 'published_at' => now()]);
        $this->overview()->assertJsonPath('data.catalogue.published', 1)->assertJsonPath('data.catalogue.drafts', 0);
        $p->delete();
        $this->overview()->assertJsonPath('data.catalogue.products', 0)->assertJsonPath('data.catalogue.recent', []);
    }

    public function test_available_stock_excludes_reservations_inactive_locations_and_variants(): void
    {
        $this->loginWith();
        $p = CatalogueProduct::create(['title' => 'Stock item', 'slug' => 'stock-item']);
        $opt = DB::table('catalogue_options')->insertGetId(['product_id' => $p->id, 'name' => 'colour']);
        $value = DB::table('catalogue_option_values')->insertGetId(['option_id' => $opt, 'value' => 'Navy']);
        $v = DB::table('catalogue_variants')->insertGetId(['product_id' => $p->id, 'colour_value_id' => $value, 'size_value_id' => $value, 'sku' => 'STOCK', 'price_minor' => 1000]);
        $location = DB::table('stock_locations')->insertGetId(['name' => 'Main', 'code' => 'MAIN']);
        $closed = DB::table('stock_locations')->insertGetId(['name' => 'Closed', 'code' => 'CLOSED', 'active' => false]);
        DB::table('stock_balances')->insert([
            ['variant_id' => $v, 'location_id' => $location, 'on_hand' => 10, 'reserved' => 3],
            ['variant_id' => $v, 'location_id' => $closed, 'on_hand' => 100, 'reserved' => 0],
        ]);
        $this->overview()->assertJsonPath('data.catalogue.variants', 1)->assertJsonPath('data.catalogue.available_units', 7);
        DB::table('catalogue_variants')->where('id', $v)->update(['active' => false]);
        $this->overview()->assertJsonPath('data.catalogue.variants', 0)->assertJsonPath('data.catalogue.available_units', 0);
        DB::table('catalogue_variants')->where('id', $v)->update(['active' => true, 'archived_at' => now()]);
        $this->overview()->assertJsonPath('data.catalogue.available_units', 0);
    }

    private function order(string $payment, int $amount, string $currency = 'GBP'): void
    {
        $bag = (string) Str::uuid();
        DB::table('commerce_bags')->insert(['id' => $bag, 'token_hash' => hash('sha256', $bag), 'items' => '[]', 'expires_at' => now()->addHour()]);
        DB::table('commerce_orders')->insert(['id' => (string) Str::uuid(), 'bag_id' => $bag, 'reference' => 'EV-'.Str::random(10), 'order_state' => 'confirmed', 'payment_state' => $payment, 'fulfilment_state' => 'unfulfilled', 'total_minor' => $amount, 'currency' => $currency, 'reservation_expires_at' => now()->addHour(), 'paid_at' => $payment === 'paid' ? now() : null, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_paid_totals_exclude_unpaid_and_exchange_credit_and_keep_currencies_separate(): void
    {
        $this->loginWith();
        $this->order('paid', 2500);
        $this->order('paid', 3000);
        $this->order('unpaid', 99999);
        $this->order('exchange_credit', 4000);
        $this->order('paid', 700, 'EUR');
        $data = $this->overview()->assertOk()->assertJsonPath('data.orders.total', 5)->json('data');
        $totals = collect($data['paid_totals'])->keyBy('currency');
        $this->assertEquals(5500, $totals['GBP']['amount_minor']);
        $this->assertEquals(700, $totals['EUR']['amount_minor']);
        $this->assertCount(5, $data['orders']['recent']);
    }

    public function test_permissions_are_applied_to_each_summary_on_the_server(): void
    {
        $this->order('paid', 10000);
        CatalogueProduct::create(['title' => 'Private product', 'slug' => 'private-product']);
        $this->loginWith(['catalogue.view']);
        $this->overview()->assertOk()->assertJsonPath('data.catalogue.products', 1)
            ->assertJsonPath('data.orders', null)->assertJsonPath('data.paid_totals', null);
        $this->loginWith([]);
        $this->overview()->assertOk()->assertJsonPath('data.catalogue', null)->assertJsonPath('data.orders', null)->assertJsonPath('data.paid_totals', null);
    }

    public function test_expired_mfa_session_is_rejected(): void
    {
        $this->loginWith();
        $this->withSession(['staff_authenticated_at' => 0]);
        $this->overview()->assertUnauthorized()->assertJsonPath('error.code', 'SESSION_EXPIRED');
    }
}
