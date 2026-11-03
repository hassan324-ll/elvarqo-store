<?php

namespace App\AfterSales;

use App\Commerce\Checkout;
use App\Commerce\Pricing;
use App\Security\StaffAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Exchanges
{
    public function issue(string $id, array $v, int $actor): array
    {
        app(Returns::class)->ready();

        return DB::transaction(function () use ($id, $v, $actor) {
            $r = app(Returns::class)->locked($id);
            $fp = hash('sha256', json_encode([$id, $v, $actor]));
            $old = DB::table('exchange_links')->find($v['operation_id']);
            if ($old) {
                abort_unless(hash_equals($old->fingerprint, $fp), 409);

                return (array) $old;
            }
            if ($r->state !== 'accepted' || $r->resolution !== 'exchange' || DB::table('exchange_links')->where('return_id', $id)->where('state', '!=', 'expired')->exists() || DB::table('refund_attempts')->where('return_id', $id)->where('state', '!=', 'sandbox_failed')->exists()) {
                Pricing::fail('return', 'Exchange requires an accepted inspection with unspent credit.');
            }
            $item = DB::table('commerce_order_items')->find($r->order_item_id);
            $line = app(Returns::class)->target($item, $v['variant_id'], $r->quantity);
            $total = $line['line_minor'];
            $difference = $total - $r->credit_minor;
            if ($v['expected_difference_minor'] !== $difference) {
                Pricing::fail('price', 'Replacement price changed. Review the current difference before confirming.');
            }
            $original = app(Checkout::class)->snapshot($r->order_id);
            unset($line['available'],$line['unavailable']);
            $s = $original;
            $s['items'] = [$line];
            $s['subtotal_minor'] = $total;
            $s['discount_minor'] = 0;
            $s['shipping_minor'] = 0;
            $s['tax_minor'] = 0;
            $s['total_minor'] = $total;
            $s['discount_code'] = '';
            $s['delivery'] = ['code' => 'exchange_test', 'name' => 'Test-only exchange delivery', 'minor' => 0, 'estimate' => 'Local sandbox'];
            $s['bag_revision'] = 1;
            $order = (string) Str::uuid();
            $bag = (string) Str::uuid();
            $funded = $difference <= 0;
            DB::table('commerce_bags')->insert(['id' => $bag, 'token_hash' => hash('sha256', random_bytes(32)), 'items' => json_encode([$v['variant_id'] => $r->quantity]), 'revision' => 1, 'expires_at' => now()->addDays(30), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('commerce_orders')->insert(['id' => $order, 'bag_id' => $bag, 'reference' => 'EV-'.strtoupper(bin2hex(random_bytes(6))), 'order_state' => $funded ? 'confirmed' : 'awaiting_payment', 'payment_method' => $funded ? 'exchange_credit' : 'exchange_top_up_pending', 'payment_state' => $funded ? 'exchange_credit' : 'awaiting_exchange_payment', 'fulfilment_state' => 'unfulfilled', 'total_minor' => $total, 'currency' => 'GBP', 'reservation_expires_at' => now()->addMinutes(30), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('commerce_order_snapshots')->insert(['order_id' => $order, 'payload' => json_encode($s), 'created_at' => now()]);
            DB::table('commerce_order_items')->insert(array_intersect_key($line, array_flip(['variant_id', 'product_id', 'sku', 'title', 'colour', 'size', 'quantity', 'unit_minor', 'line_minor', 'currency'])) + ['order_id' => $order, 'created_at' => now()]);
            foreach (['shipping' => 'shipping_address', 'billing' => 'billing_address'] as $kind => $key) {
                DB::table('commerce_order_addresses')->insert($s[$key] + ['order_id' => $order, 'kind' => $kind, 'created_at' => now()]);
            }DB::table('commerce_payments')->insert(['order_id' => $order, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('commerce_checkout_attempts')->insert(['id' => (string) Str::uuid(), 'bag_id' => $bag, 'order_id' => $order, 'fingerprint' => $fp, 'created_at' => now()]);
            app(Checkout::class)->reserve($order, $s);
            if ($funded) {
                app(Checkout::class)->settle(DB::table('commerce_orders')->find($order), true);
            }
            DB::table('exchange_links')->insert(['id' => $v['operation_id'], 'return_id' => $id, 'replacement_order_id' => $order, 'fingerprint' => $fp, 'credit_minor' => min($total, $r->credit_minor), 'difference_minor' => $difference, 'state' => $funded ? 'issued' : 'awaiting_top_up', 'created_at' => now()]);
            $owner = DB::table('customer_orders')->where('order_id', $r->order_id)->first();
            if ($owner) {
                DB::table('customer_orders')->insert(['order_id' => $order, 'customer_id' => $owner->customer_id, 'created_at' => now()]);
            }
            app(Returns::class)->event($id, $funded ? 'exchange_issued' : 'exchange_top_up_deferred', ['replacement_order_id' => $order, 'difference_minor' => $difference]);
            StaffAudit::record('return.exchange_created', $actor, 'return:'.$id);

            return (array) DB::table('exchange_links')->find($v['operation_id']);
        }, 5);
    }

    public function expire(): void
    {
        foreach (DB::table('exchange_links as e')->join('commerce_orders as o', 'o.id', '=', 'e.replacement_order_id')->where('e.state', 'awaiting_top_up')->where('o.reservation_expires_at', '<=', now())->pluck('e.id') as $id) {
            DB::transaction(function () use ($id) {
                $e = DB::table('exchange_links')->find($id);
                app(Returns::class)->locked($e->return_id);
                $e = DB::table('exchange_links')->where('id', $id)->lockForUpdate()->first();
                if ($e->state !== 'awaiting_top_up') {
                    return;
                }$o = DB::table('commerce_orders')->where('id', $e->replacement_order_id)->lockForUpdate()->first();
                app(Checkout::class)->settle($o, false);
                DB::table('commerce_orders')->where('id', $o->id)->update(['payment_state' => 'expired', 'order_state' => 'cancelled', 'updated_at' => now()]);
                DB::table('exchange_links')->where('id', $id)->update(['state' => 'expired']);
                app(Returns::class)->event($e->return_id, 'exchange_hold_expired');
            }, 5);
        }
    }
}
