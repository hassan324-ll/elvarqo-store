<?php

namespace App\Commerce;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Pricing
{
    public static function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    public function lines(array $items): array
    {
        $lines = [];
        foreach ($items as $id => $quantity) {
            $v = DB::table('catalogue_variants as v')->join('catalogue_products as p', 'p.id', '=', 'v.product_id')->join('catalogue_option_values as c', 'c.id', '=', 'v.colour_value_id')->join('catalogue_option_values as s', 's.id', '=', 'v.size_value_id')->where('v.id', $id)->where('v.active', true)->whereNull('v.archived_at')->whereNull('p.deleted_at')->where('p.status', 'published')->select('v.id', 'v.product_id', 'v.sku', 'v.price_minor', 'v.currency', 'p.title', 'p.slug', 'c.value as colour', 's.value as size')->first();
            if (! $v) {
                $lines[] = ['variant_id' => (int) $id, 'quantity' => $quantity, 'unavailable' => true];

                continue;
            }
            $available = (int) DB::table('stock_balances as b')->join('stock_locations as l', 'l.id', '=', 'b.location_id')->where('l.active', true)->where('b.variant_id', $id)->sum(DB::raw('b.on_hand-b.reserved'));
            $lines[] = ['variant_id' => $v->id, 'product_id' => $v->product_id, 'sku' => $v->sku, 'title' => $v->title, 'slug' => $v->slug, 'colour' => $v->colour, 'size' => $v->size, 'quantity' => $quantity, 'unit_minor' => $v->price_minor, 'line_minor' => $v->price_minor * $quantity, 'currency' => $v->currency, 'available' => $available, 'unavailable' => $v->currency !== 'GBP' || $available < $quantity];
        }

        return $lines;
    }

    public function quote(object $bag, array $input): array
    {
        $lines = $this->lines(json_decode($bag->items, true));
        if (! $lines || array_filter($lines, fn ($l) => $l['unavailable'])) {
            self::fail('items', 'Some pieces are unavailable or exceed current stock. Review your bag.');
        }
        $subtotal = array_sum(array_column($lines, 'line_minor'));
        $discount = 0;
        $code = strtoupper(trim($input['discount_code'] ?? ''));
        $rule = config('commerce.discount');
        if ($code !== '') {
            if ($code !== $rule['code'] || $subtotal < $rule['minimum_minor'] || now()->lt($rule['starts_at']) || now()->gte($rule['ends_at'])) {
                self::fail('discount_code', 'This test code is invalid, expired or below its minimum.');
            }
            $discount = intdiv($subtotal * $rule['basis_points'] + 5000, 10000);
        }
        $service = $input['delivery_method'];
        $rate = config('commerce.delivery')[$service] ?? null;
        if (! $rate) {
            self::fail('delivery_method', 'Choose an available delivery method.');
        }
        $net = $subtotal - $discount + $rate['minor'];
        $tax = intdiv($net * config('commerce.tax_basis_points') + 5000, 10000);
        if ($net + $tax > 99999999) {
            self::fail('total', 'This bag exceeds the sandbox payment limit. Reduce the quantities.');
        }
        $snapshot = ['currency' => 'GBP', 'test_rates' => true, 'tax_note' => 'Test tax assumption only; merchant tax review pending.', 'tax_basis_points' => config('commerce.tax_basis_points'), 'items' => array_map(function ($l) {
            unset($l['available'],$l['unavailable']);

            return $l;
        }, $lines), 'contact' => ['email' => strtolower(trim($input['email']))], 'shipping_address' => $input['address'], 'billing_address' => $input['billing_address'] ?? $input['address'], 'delivery' => ['code' => $service] + $rate, 'discount_code' => $code, 'subtotal_minor' => $subtotal, 'discount_minor' => $discount, 'shipping_minor' => $rate['minor'], 'tax_minor' => $tax, 'total_minor' => $net + $tax, 'bag_revision' => $bag->revision];

        return ['snapshot' => $snapshot, 'quote_hash' => hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))];
    }
}
