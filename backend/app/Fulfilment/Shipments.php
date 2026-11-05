<?php

namespace App\Fulfilment;

use App\Commerce\Pricing;
use App\Security\StaffAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Shipments
{
    public function order(string $id): object
    {
        $o = DB::table('commerce_orders')->where('id', $id)->lockForUpdate()->first();
        abort_unless($o, 404);
        if (! ($o->payment_state === 'paid' || ($o->payment_state === 'exchange_credit' && DB::table('exchange_links')->where('replacement_order_id', $id)->where('state', 'issued')->exists())) || $o->order_state !== 'confirmed' || $o->fulfilment_state === 'on_hold') {
            Pricing::fail('order', 'Only confirmed paid or funded exchange orders that are not on hold can be fulfilled.');
        }

        return $o;
    }

    public function remaining(string $id): array
    {
        return DB::table('commerce_order_items')->where('order_id', $id)->orderBy('id')->get()->map(function ($i) {
            $used = (int) DB::table('fulfilment_items as i')->join('order_fulfilments as f', 'f.id', '=', 'i.fulfilment_id')->where('i.order_item_id', $i->id)->where('f.state', '!=', 'cancelled')->sum('i.quantity');

            return ['order_item_id' => $i->id, 'variant_id' => $i->variant_id, 'title' => $i->title, 'sku' => $i->sku, 'colour' => $i->colour, 'size' => $i->size, 'ordered' => $i->quantity, 'allocated' => $used, 'remaining' => $i->quantity - $used];
        })->all();
    }

    private function selection(string $order, array $v): array
    {
        $location = DB::table('stock_locations')->where('id', $v['location_id'])->where('active', true)->first();
        if (! $location) {
            Pricing::fail('location_id', 'Choose an active dispatch location.');
        }
        $remaining = collect($this->remaining($order))->keyBy('order_item_id');
        $snapshot = json_decode(DB::table('commerce_order_snapshots')->where('order_id', $order)->value('payload'), true);
        $stockModes = collect($snapshot['items'] ?? [])->keyBy('variant_id');
        $items = [];
        foreach ($v['items'] as $row) {
            $id = (int) $row['order_item_id'];
            $qty = (int) $row['quantity'];
            $i = $remaining->get($id);
            if (! $i || $qty < 1 || $qty > $i['remaining'] || isset($items[$id])) {
                Pricing::fail('items', 'Items must belong to this order and not exceed unallocated quantities.');
            }
            $committed = (int) DB::table('commerce_reservations as r')->join('stock_balances as b', 'b.id', '=', 'r.balance_id')->where('r.order_id', $order)->where('r.variant_id', $i['variant_id'])->where('r.state', 'committed')->where('b.location_id', $location->id)->sum('r.quantity');
            $used = (int) DB::table('fulfilment_items as i')->join('order_fulfilments as f', 'f.id', '=', 'i.fulfilment_id')->where('f.order_id', $order)->where('f.location_id', $location->id)->where('f.state', '!=', 'cancelled')->where('i.order_item_id', $id)->sum('i.quantity');
            $tracked = ($stockModes->get($i['variant_id'])['stock_tracked'] ?? true) !== false;
            if ($tracked && $qty > $committed - $used) {
                Pricing::fail('location_id', 'This location has insufficient committed units for the selected order items.');
            }$items[$id] = ['order_item_id' => $id, 'variant_id' => $i['variant_id'], 'quantity' => $qty];
        }ksort($items);

        return ['items' => array_values($items), 'location_id' => $location->id, 'location_name' => $location->name, 'service' => $v['service'], 'parcel' => array_map(fn ($n) => (int) $n, $v['parcel']), 'instructions' => trim($v['instructions'] ?? ''), 'destination' => json_decode(DB::table('commerce_order_snapshots')->where('order_id', $order)->value('payload'), true)['shipping_address'], 'sandbox' => true];
    }

    public function quote(string $order, array $v, int $actor): array
    {
        return DB::transaction(function () use ($order, $v, $actor) {
            $this->order($order);
            $payload = $this->selection($order, $v);
            $rate = config('fulfilment.services')[$v['service']] ?? null;
            if (! $rate) {
                Pricing::fail('service', 'Choose a sandbox courier service.');
            }$id = (string) Str::uuid();
            $payload['service_name'] = $rate['name'];
            $cost = $rate['cost_minor'] + max(0, (int) ceil($payload['parcel']['weight_grams'] / 1000) - 1) * 50;
            DB::table('shipment_quotes')->insert(['id' => $id, 'order_id' => $order, 'staff_user_id' => $actor, 'payload' => json_encode($payload), 'fingerprint' => hash('sha256', json_encode($payload)), 'cost_minor' => $cost, 'expires_at' => now()->addMinutes(10), 'created_at' => now(), 'updated_at' => now()]);

            return ['id' => $id, 'payload' => $payload, 'cost_minor' => $cost, 'currency' => 'GBP', 'expires_at' => now()->addMinutes(10)->toISOString(), 'warning' => 'LOCAL SANDBOX ONLY — no postage purchase or courier collection.'];
        }, 5);
    }

    public function prepare(string $order, string $quote, int $actor): object
    {
        return DB::transaction(function () use ($order, $quote, $actor) {
            $this->order($order);
            $old = DB::table('order_fulfilments')->where('quote_id', $quote)->first();
            if ($old) {
                abort_unless($old->order_id === $order, 404);

                return $old;
            }
            $q = DB::table('shipment_quotes')->where('id', $quote)->where('order_id', $order)->first();
            abort_unless($q, 404);
            if (now()->gte($q->expires_at)) {
                Pricing::fail('quote', 'This shipping quote expired. Obtain a new quote.');
            }$payload = json_decode($q->payload, true);
            $this->selection($order, ['items' => $payload['items'], 'location_id' => $payload['location_id'], 'service' => $payload['service'], 'parcel' => $payload['parcel'], 'instructions' => $payload['instructions']]);
            $id = (string) Str::uuid();
            DB::table('order_fulfilments')->insert(['id' => $id, 'order_id' => $order, 'quote_id' => $quote, 'staff_user_id' => $actor, 'location_id' => $payload['location_id'], 'state' => 'draft', 'service' => $payload['service'], 'cost_minor' => $q->cost_minor, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($payload['items'] as $row) {
                DB::table('fulfilment_items')->insert(['fulfilment_id' => $id, 'order_item_id' => $row['order_item_id'], 'quantity' => $row['quantity']]);
            }DB::table('fulfilment_parcels')->insert($payload['parcel'] + ['fulfilment_id' => $id, 'instructions' => $payload['instructions']]);
            StaffAudit::record('shipment.prepared', $actor, 'shipment:'.$id);

            return DB::table('order_fulfilments')->find($id);
        }, 5);
    }

    private function attempt(string $id, int $actor, string $action, string $outcome, ?string $reference = null): void
    {
        DB::table('courier_booking_attempts')->insert(['id' => (string) Str::uuid(), 'fulfilment_id' => $id, 'staff_user_id' => $actor, 'action' => $action, 'outcome' => $outcome, 'provider_reference' => $reference, 'created_at' => now()]);
    }

    public function book(string $id, int $actor): array
    {
        $f = DB::transaction(function () use ($id) {
            $f = DB::table('order_fulfilments')->find($id);
            abort_unless($f, 404);
            $this->order($f->order_id);
            $f = DB::table('order_fulfilments')->where('id', $id)->lockForUpdate()->first();
            if (in_array($f->state, ['booked', 'dispatched', 'in_transit', 'out_for_delivery', 'delivered', 'exception'])) {
                return $f;
            }if (! in_array($f->state, ['draft', 'booking', 'unknown', 'failed'])) {
                Pricing::fail('shipment', 'This shipment is not eligible for booking.');
            }DB::table('order_fulfilments')->where('id', $id)->update(['state' => 'booking', 'updated_at' => now()]);

            return $f;
        }, 5);
        if (! in_array($f->state, ['draft', 'booking', 'unknown', 'failed'])) {
            return $this->detail($id);
        }
        try {
            $receipt = app(SandboxCourier::class)->book($f);
            DB::transaction(function () use ($id, $actor, $receipt) {
                $f = DB::table('order_fulfilments')->find($id);
                $this->order($f->order_id);
                $f = DB::table('order_fulfilments')->where('id', $id)->lockForUpdate()->first();
                if (in_array($f->state, ['booked', 'dispatched', 'in_transit', 'out_for_delivery', 'delivered', 'exception', 'cancelled'])) {
                    return;
                }
                $label = $this->labelSvg($f, $receipt);
                $path = 'shipment-labels/'.$id.'.svg';
                $disk = Storage::disk('local');
                $disk->makeDirectory('shipment-labels');
                $tmp = $disk->path($path.'.'.bin2hex(random_bytes(8)).'.tmp');
                if (file_put_contents($tmp, $label, LOCK_EX) === false || ! rename($tmp, $disk->path($path))) {
                    throw new \RuntimeException('Could not save sandbox label.');
                }
                DB::table('fulfilment_labels')->insertOrIgnore(['fulfilment_id' => $id, 'path' => $path, 'sha256' => hash('sha256', $label), 'created_at' => now()]);
                DB::table('order_fulfilments')->where('id', $id)->update(['state' => 'booked', 'provider_reference' => $receipt->reference, 'tracking_number' => $receipt->tracking_number, 'last_error' => null, 'updated_at' => now()]);
                $this->attempt($id, $actor, 'book', 'booked', $receipt->reference);
                StaffAudit::record('shipment.booked_sandbox', $actor, 'shipment:'.$id);
            }, 5);
        } catch (\Throwable $e) {
            DB::transaction(function () use ($id, $actor, $e) {
                $f = DB::table('order_fulfilments')->where('id', $id)->lockForUpdate()->first();
                if (in_array($f->state, ['booking', 'draft', 'unknown', 'failed'])) {
                    DB::table('order_fulfilments')->where('id', $id)->update(['state' => $e instanceof \DomainException ? 'failed' : 'unknown', 'last_error' => $e instanceof \DomainException ? 'Sandbox provider rejected this booking.' : 'Outcome uncertain. Retry this same booking; do not create another parcel.', 'updated_at' => now()]);
                    $this->attempt($id, $actor, 'book', $e instanceof \DomainException ? 'rejected' : 'unknown');
                }
            }, 5);
        }

        return $this->detail($id);
    }

    private function labelSvg(object $f, object $receipt): string
    {
        $p = json_decode(DB::table('shipment_quotes')->where('id', $f->quote_id)->value('payload'), true);
        $a = $p['destination'];
        $lines = ['ELVARQO — LOCAL COURIER SANDBOX', 'NOT VALID FOR POSTAGE', $receipt->reference, $receipt->tracking_number, $a['first_name'].' '.$a['last_name'], $a['line1'], $a['line2'] ?? '', $a['city'].' '.$a['postcode'].' '.$a['country'], 'Origin: '.$p['location_name'], 'Service: '.$p['service_name'], 'Weight: '.$p['parcel']['weight_grams'].'g', 'No carrier booking or collection exists.'];
        $rows = [];
        foreach ($lines as $i => $text) {
            foreach (mb_str_split($text, 48) as $chunk) {
                $rows[] = ['text' => $chunk, 'large' => $i === 1];
            }
        }
        $height = max(820, 80 + count($rows) * 38);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="'.$height.'" viewBox="0 0 600 '.$height.'"><rect width="600" height="'.$height.'" fill="white" stroke="black"/><g font-family="Arial,sans-serif" fill="black">';
        foreach ($rows as $i => $row) {
            $svg .= '<text x="24" y="'.(45 + $i * 38).'" font-size="'.($row['large'] ? '28' : '16').'">'.htmlspecialchars($row['text'], ENT_XML1 | ENT_QUOTES, 'UTF-8').'</text>';
        }

        return $svg.'</g></svg>';
    }

    public function cancel(string $id, int $actor): array
    {
        return DB::transaction(function () use ($id, $actor) {
            $f = DB::table('order_fulfilments')->find($id);
            abort_unless($f, 404);
            $this->order($f->order_id);
            $f = DB::table('order_fulfilments')->where('id', $id)->lockForUpdate()->first();
            if ($f->state === 'cancelled') {
                return $this->detail($id);
            }if (! in_array($f->state, ['draft', 'failed', 'booked'])) {
                Pricing::fail('shipment', 'Only an unattempted/failed draft or a booked label before dispatch can be cancelled. Resolve uncertain bookings first.');
            }app(SandboxCourier::class)->void($f);
            DB::table('order_fulfilments')->where('id', $id)->update(['state' => 'cancelled', 'updated_at' => now()]);
            $this->attempt($id, $actor, 'cancel', 'void_confirmed', $f->provider_reference);
            StaffAudit::record('shipment.cancelled_sandbox', $actor, 'shipment:'.$id);
            $this->aggregate($f->order_id);

            return $this->detail($id);
        }, 5);
    }

    public function dispatch(string $id, int $actor): array
    {
        return DB::transaction(function () use ($id, $actor) {
            $f = DB::table('order_fulfilments')->find($id);
            abort_unless($f, 404);
            $this->order($f->order_id);
            $f = DB::table('order_fulfilments')->where('id', $id)->lockForUpdate()->first();
            if (in_array($f->state, ['dispatched', 'in_transit', 'out_for_delivery', 'delivered', 'exception'])) {
                return $this->detail($id);
            }if ($f->state !== 'booked') {
                Pricing::fail('shipment', 'Obtain a confirmed label before dispatch.');
            }DB::table('order_fulfilments')->where('id', $id)->update(['state' => 'dispatched', 'dispatched_at' => now(), 'progress_rank' => 10, 'updated_at' => now()]);
            Notifications::queue($f->order_id, 'dispatch', 'dispatch:'.$id, [], $id);
            StaffAudit::record('shipment.dispatched', $actor, 'shipment:'.$id);
            $this->aggregate($f->order_id);

            return $this->detail($id);
        }, 5);
    }

    public function aggregate(string $order): void
    {
        $rows = DB::table('order_fulfilments')->where('order_id', $order)->where('state', '!=', 'cancelled')->get();
        $ordered = (int) DB::table('commerce_order_items')->where('order_id', $order)->sum('quantity');
        $dispatched = 0;
        $delivered = 0;
        foreach ($rows as $f) {
            $qty = (int) DB::table('fulfilment_items')->where('fulfilment_id', $f->id)->sum('quantity');
            if ($f->dispatched_at) {
                $dispatched += $qty;
            }if ($f->state === 'delivered') {
                $delivered += $qty;
            }
        }$state = $delivered === $ordered && $ordered ? 'delivered' : ($dispatched === $ordered && $ordered ? 'fulfilled' : ($dispatched ? 'partially_fulfilled' : 'unfulfilled'));
        DB::table('commerce_orders')->where('id', $order)->where('fulfilment_state', '!=', 'on_hold')->update(['fulfilment_state' => $state, 'updated_at' => now()]);
    }

    public function detail(string $id): array
    {
        $f = DB::table('order_fulfilments')->find($id);
        abort_unless($f, 404);

        return (array) $f + ['items' => DB::table('fulfilment_items')->where('fulfilment_id', $id)->get(), 'parcel' => DB::table('fulfilment_parcels')->where('fulfilment_id', $id)->first(), 'attempts' => DB::table('courier_booking_attempts')->where('fulfilment_id', $id)->orderBy('created_at')->get(), 'events' => DB::table('courier_tracking_events')->where('fulfilment_id', $id)->orderBy('occurred_at')->get(), 'label_available' => $f->state !== 'cancelled' && DB::table('fulfilment_labels')->where('fulfilment_id', $id)->exists(), 'sandbox' => true];
    }
}
