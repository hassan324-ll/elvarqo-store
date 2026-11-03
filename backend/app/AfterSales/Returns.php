<?php

namespace App\AfterSales;

use App\Commerce\Checkout;
use App\Commerce\Pricing;
use App\Fulfilment\Notifications;
use App\Security\StaffAudit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Returns
{
    public const POLICY = 'TEST ONLY: 30 days after verified delivery; inspection required. Merchandise credit excludes outbound delivery and tax. Local refunds move no real money.';

    public function ready(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 503, 'Test-only after-sales policy is disabled outside local/testing.');
    }

    public function event(string $id, string $kind, array $payload = []): void
    {
        DB::table('after_sales_events')->insert(['id' => (string) Str::uuid(), 'return_id' => $id, 'kind' => $kind, 'payload' => json_encode($payload), 'created_at' => now()]);
    }

    public function deliveredUnits(object $item): array
    {
        $claimed = DB::table('return_unit_claims')->where('order_item_id', $item->id)->get()->map(fn ($c) => $c->fulfilment_item_id.':'.$c->delivery_unit)->flip()->all();
        $eligible = [];
        foreach (DB::table('fulfilment_items as i')->join('order_fulfilments as f', 'f.id', '=', 'i.fulfilment_id')->where('i.order_item_id', $item->id)->where('f.state', 'delivered')->whereNotNull('f.last_event_at')->orderBy('i.id')->get(['i.id', 'i.quantity', 'f.last_event_at']) as $f) {
            if (now()->gt(Carbon::parse($f->last_event_at)->addDays(30))) {
                continue;
            }
            for ($n = 1; $n <= $f->quantity; $n++) {
                if (! array_key_exists($f->id.':'.$n, $claimed)) {
                    $eligible[] = ['fulfilment_item_id' => $f->id, 'delivery_unit' => $n];
                }
            }
        }

        return $eligible;
    }

    public function units(object $item): array
    {
        $claimed = DB::table('return_unit_claims')->where('order_item_id', $item->id)->pluck('unit')->all();
        $free = array_values(array_diff(range(1, $item->quantity), $claimed));

        return array_slice($free, 0, count($this->deliveredUnits($item)));
    }

    public function credit(object $item, array $units): int
    {
        $s = app(Checkout::class)->snapshot($item->order_id);
        $offset = 0;
        $discount = 0;
        foreach (DB::table('commerce_order_items')->where('order_id', $item->order_id)->orderBy('id')->get() as $line) {
            $before = intdiv($offset * $s['discount_minor'], max(1, $s['subtotal_minor']));
            $offset += $line->line_minor;
            if ($line->id === $item->id) {
                $discount = intdiv($offset * $s['discount_minor'], max(1, $s['subtotal_minor'])) - $before;
                break;
            }
        }
        $net = $item->line_minor - $discount;
        $sum = 0;
        foreach ($units as $u) {
            $sum += intdiv($net * $u, $item->quantity) - intdiv($net * ($u - 1), $item->quantity);
        }

        return $sum;
    }

    public function view(object $o): array
    {
        $paid = $o->payment_state === 'paid';
        $items = DB::table('commerce_order_items')->where('order_id', $o->id)->orderBy('id')->get()->map(function ($i) use ($paid) {
            $u = $paid ? $this->units($i) : [];

            return ['id' => $i->id, 'title' => $i->title, 'sku' => $i->sku, 'size' => $i->size, 'colour' => $i->colour, 'quantity' => $i->quantity, 'eligible_quantity' => count($u), 'eligible_credit_minor' => $this->credit($i, $u), 'exchange_options' => DB::table('catalogue_variants')->where('product_id', $i->product_id)->where('active', true)->whereNull('archived_at')->orderBy('id')->get(['id', 'sku', 'price_minor'])->all()];
        })->all();

        return ['policy' => self::POLICY, 'items' => $items, 'returns' => DB::table('after_sales_returns')->where('order_id', $o->id)->orderBy('created_at')->get()->map(fn ($r) => $this->detail($r, false))->all()];
    }

    public function detail(object $r, bool $staff = true): array
    {
        $inspection = DB::table('return_inspections')->where('return_id', $r->id)->first();
        $exchanges = DB::table('exchange_links as e')->join('commerce_orders as o', 'o.id', '=', 'e.replacement_order_id')->where('return_id', $r->id)->orderBy('e.created_at')->get(['e.id', 'e.replacement_order_id', 'e.credit_minor', 'e.difference_minor', 'e.state', 'o.reference', 'o.payment_state', 'o.order_state']);
        $refunds = DB::table('refund_attempts')->where('return_id', $r->id)->orderBy('created_at')->get(['id', 'amount_minor', 'currency', 'state', 'last_error', 'created_at']);
        $data = ['id' => $r->id, 'order_id' => $r->order_id, 'order_item_id' => $r->order_item_id, 'quantity' => $r->quantity, 'credit_minor' => $r->credit_minor, 'reason' => $r->reason, 'resolution' => $r->resolution, 'exchange_variant_id' => $r->exchange_variant_id, 'comments' => $r->comments, 'state' => $r->state, 'created_at' => $r->created_at, 'inspection' => $inspection ? ['outcome' => $inspection->outcome, 'received_quantity' => $inspection->received_quantity, 'reason' => $inspection->reason, 'created_at' => $inspection->created_at] : null, 'exchanges' => $exchanges, 'refunds' => $refunds, 'refundable_minor' => app(Refunds::class)->remaining($r), 'timeline' => DB::table('after_sales_events')->where('return_id', $r->id)->orderBy('created_at')->get(['kind', 'created_at'])];
        if ($staff) {
            $data['refunds'] = DB::table('refund_attempts as a')->leftJoin('sandbox_refund_receipts as p', 'p.attempt_id', '=', 'a.id')->where('a.return_id', $r->id)->orderBy('a.created_at')->get(['a.id', 'a.amount_minor', 'a.currency', 'a.state', 'a.last_error', 'a.created_at', 'a.reason', 'p.provider_reference']);
            $data['reference'] = DB::table('commerce_orders')->where('id', $r->order_id)->value('reference');
            $data['item'] = DB::table('commerce_order_items')->find($r->order_item_id);
            $data['locations'] = DB::table('stock_locations')->where('active', true)->get(['id', 'name']);
            $data['exchange_options'] = $this->viewOptions($r);
        }

        return $data;
    }

    public function viewOptions(object $r): array
    {
        $item = DB::table('commerce_order_items')->find($r->order_item_id);
        $variants = DB::table('catalogue_variants')->where('product_id', $item->product_id)->where('active', true)->whereNull('archived_at')->pluck('id');

        return app(Pricing::class)->lines(array_fill_keys($variants->all(), $r->quantity));
    }

    public function request(object $o, Request $request): array
    {
        $this->ready();
        $v = $request->validate(['operation_id' => 'required|uuid', 'order_item_id' => 'required|integer|min:1', 'quantity' => 'required|integer|min:1|max:1000', 'reason' => 'required|in:size,changed_mind,damaged,wrong_item', 'resolution' => 'required|in:refund,exchange', 'exchange_variant_id' => 'nullable|required_if:resolution,exchange|integer|min:1', 'comments' => 'nullable|string|max:1500', 'confirmed' => 'accepted']);

        return DB::transaction(function () use ($o, $v) {
            $o = DB::table('commerce_orders')->where('id', $o->id)->lockForUpdate()->first();
            $fp = hash('sha256', json_encode($v));
            $old = DB::table('after_sales_returns')->find($v['operation_id']);
            if ($old) {
                abort_unless($old->order_id === $o->id && hash_equals($old->fingerprint, $fp), 409);

                return $this->detail($old, false);
            }if ($o->payment_state !== 'paid') {
                Pricing::fail('order', 'Only verified paid and delivered units qualify under the test policy.');
            }$item = DB::table('commerce_order_items')->where('order_id', $o->id)->where('id', $v['order_item_id'])->first();
            abort_unless($item, 404);
            $units = array_slice($this->units($item), 0, $v['quantity']);
            if (count($units) !== $v['quantity']) {
                Pricing::fail('quantity', 'Not enough unclaimed delivered units within the test return window.');
            }
            if ($v['resolution'] === 'exchange') {
                $this->target($item, $v['exchange_variant_id'], $v['quantity']);
            }
            DB::table('after_sales_returns')->insert(['id' => $v['operation_id'], 'order_id' => $o->id, 'order_item_id' => $item->id, 'fingerprint' => $fp, 'quantity' => $v['quantity'], 'units' => json_encode($units), 'credit_minor' => $this->credit($item, $units), 'reason' => $v['reason'], 'resolution' => $v['resolution'], 'exchange_variant_id' => $v['exchange_variant_id'] ?? null, 'comments' => trim($v['comments'] ?? ''), 'state' => 'requested', 'created_at' => now(), 'updated_at' => now()]);
            $proofs = array_slice($this->deliveredUnits($item), 0, $v['quantity']);
            foreach ($units as $index => $u) {
                DB::table('return_unit_claims')->insert(['order_item_id' => $item->id, 'unit' => $u, 'return_id' => $v['operation_id']] + $proofs[$index]);
            }$this->event($v['operation_id'], 'requested', ['delivery_units' => $proofs, 'credit_units' => $units]);
            Notifications::queue($o->id, 'return_requested', 'return:'.$v['operation_id']);

            return $this->detail(DB::table('after_sales_returns')->find($v['operation_id']), false);
        }, 5);
    }

    public function target(object $item, int $variant, int $quantity): array
    {
        $line = app(Pricing::class)->lines([$variant => $quantity])[0];
        if (($line['unavailable'] ?? true) || $line['product_id'] !== $item->product_id) {
            Pricing::fail('exchange_variant_id', 'That replacement is unavailable. Choose an in-stock variant of the same product.');
        }

        return $line;
    }

    public function locked(string $id): object
    {
        $r = DB::table('after_sales_returns')->find($id);
        abort_unless($r, 404);
        DB::table('commerce_orders')->where('id', $r->order_id)->lockForUpdate()->first();

        return DB::table('after_sales_returns')->where('id', $id)->lockForUpdate()->first();
    }

    public function inspect(string $id, array $v, int $actor): array
    {
        $this->ready();

        return DB::transaction(function () use ($id, $v, $actor) {
            $r = $this->locked($id);
            $fp = hash('sha256', json_encode([$v, $actor]));
            $old = DB::table('return_inspections')->where('return_id', $id)->first();
            if ($old) {
                abort_unless(hash_equals($old->fingerprint, $fp), 409);

                return $this->detail($r);
            }if ($r->state !== 'requested' || $v['received_quantity'] !== $r->quantity) {
                Pricing::fail('quantity', 'Confirm physical receipt of all units in this request before inspection.');
            }abort_unless(DB::table('stock_locations')->where('id', $v['location_id'])->where('active', true)->exists(), 422);
            DB::table('return_inspections')->insert(['return_id' => $id, 'staff_user_id' => $actor, 'outcome' => $v['outcome'], 'received_quantity' => $v['received_quantity'], 'location_id' => $v['location_id'], 'reason' => trim($v['reason']), 'fingerprint' => $fp, 'created_at' => now()]);
            if ($v['outcome'] === 'accepted_resellable') {
                $item = DB::table('commerce_order_items')->find($r->order_item_id);
                DB::table('catalogue_variants')->where('id', $item->variant_id)->lockForUpdate()->first();
                DB::table('stock_balances')->insertOrIgnore(['variant_id' => $item->variant_id, 'location_id' => $v['location_id'], 'on_hand' => 0, 'reserved' => 0]);
                $b = DB::table('stock_balances')->where('variant_id', $item->variant_id)->where('location_id', $v['location_id'])->lockForUpdate()->first();
                DB::table('stock_balances')->where('id', $b->id)->increment('on_hand', $r->quantity);
                DB::table('after_sales_stock_events')->insert(['return_id' => $id, 'balance_id' => $b->id, 'delta' => $r->quantity, 'on_hand_after' => $b->on_hand + $r->quantity, 'created_at' => now()]);
            }
            if ($v['outcome'] === 'rejected') {
                DB::table('return_unit_claims')->where('return_id', $id)->delete();
            }DB::table('after_sales_returns')->where('id', $id)->update(['state' => $v['outcome'] === 'rejected' ? 'rejected' : 'accepted', 'updated_at' => now()]);
            $this->event($id, 'inspection_'.$v['outcome']);
            StaffAudit::record('return.inspected', $actor, 'return:'.$id);

            return $this->detail(DB::table('after_sales_returns')->find($id));
        }, 5);
    }

    public function resolution(string $id, array $v, int $actor): array
    {
        $this->ready();

        return DB::transaction(function () use ($id, $v, $actor) {
            $r = $this->locked($id);
            if (DB::table('exchange_links')->where('return_id', $id)->where('state', '!=', 'expired')->exists()) {
                Pricing::fail('exchange', 'An issued or pending replacement cannot be switched to a refund.');
            }DB::table('after_sales_returns')->where('id', $id)->update(['resolution' => 'refund', 'updated_at' => now()]);
            $this->event($id, 'resolution_refund', ['reason' => $v['reason']]);
            StaffAudit::record('return.resolution_changed', $actor, 'return:'.$id);

            return $this->detail(DB::table('after_sales_returns')->find($id));
        }, 5);
    }
}
