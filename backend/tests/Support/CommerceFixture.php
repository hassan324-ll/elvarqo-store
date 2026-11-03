<?php

namespace Tests\Support;

use App\Models\CatalogueProduct;
use App\Models\CatalogueVariant;
use App\Models\StockLocation;
use Illuminate\Support\Facades\DB;

class CommerceFixture
{
    public static function seed(int $stock = 5): int
    {
        $p = CatalogueProduct::create(['title' => 'QA checkout polo', 'slug' => 'qa-checkout-polo', 'status' => 'published', 'product_type' => 'Knitwear', 'description' => 'Isolated checkout test fixture', 'material' => 'Test cotton', 'fit' => 'Test fit', 'care' => 'Test care', 'measurements' => [], 'published_at' => now(), 'first_published_at' => now()]);
        DB::table('catalogue_slugs')->insert(['slug' => $p->slug, 'product_id' => $p->id]);
        $values = [];
        foreach (['colour' => 'Navy', 'size' => 'M'] as $name => $value) {
            $o = DB::table('catalogue_options')->insertGetId(['product_id' => $p->id, 'name' => $name]);
            $values[$name] = DB::table('catalogue_option_values')->insertGetId(['option_id' => $o, 'value' => $value]);
        }
        $v = CatalogueVariant::create(['product_id' => $p->id, 'colour_value_id' => $values['colour'], 'size_value_id' => $values['size'], 'sku' => 'QA-CHECKOUT-M', 'price_minor' => 6800, 'currency' => 'GBP', 'active' => true]);
        $l = StockLocation::create(['name' => 'Isolated test warehouse', 'code' => 'CHECKOUT']);
        DB::table('stock_balances')->insert(['variant_id' => $v->id, 'location_id' => $l->id, 'on_hand' => $stock, 'reserved' => 0]);

        return $v->id;
    }

    public static function details(int $revision = 2): array
    {
        return ['revision' => $revision, 'email' => 'sandbox-guest@example.test', 'address' => ['first_name' => 'QA', 'last_name' => 'Guest', 'line1' => '1 Test Street', 'line2' => '', 'city' => 'London', 'postcode' => 'SW1A1AA', 'country' => 'GB'], 'delivery_method' => 'standard', 'discount_code' => 'TEST10'];
    }

    public static function session(array $request, string $status = 'open', string $payment = 'unpaid'): array
    {
        return ['id' => 'cs_test_'.str_replace('-', '', $request['metadata']['order_id']), 'livemode' => false, 'mode' => 'payment', 'client_reference_id' => $request['client_reference_id'], 'metadata' => $request['metadata'], 'currency' => 'gbp', 'amount_total' => $request['line_items'][0]['price_data']['unit_amount'], 'status' => $status, 'payment_status' => $payment, 'url' => 'https://checkout.stripe.com/c/pay/cs_test_fixture'];
    }
}
