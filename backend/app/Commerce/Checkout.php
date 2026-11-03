<?php

namespace App\Commerce;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Checkout
{
    public function start(Request $r, array $v, bool $manual = false): array
    {
        $wise = config('commerce.provider') === 'wise_bank_transfer';
        abort_unless($wise || config('commerce.provider') === 'stripe_sandbox', 503, 'Payment provider is not configured.');
        if ($wise) {
            app(WiseBankTransfer::class)->ready();
        } elseif (! $manual) {
            app(StripeSandbox::class)->ready();
        }
        $fingerprint = hash('sha256', json_encode($v));
        $id = DB::transaction(function () use ($r, $v, $fingerprint, $manual, $wise) {
            $bag = app(Bags::class)->owned($r, true);
            $old = DB::table('commerce_checkout_attempts')->find($v['attempt_id']);
            if ($old) {
                if ($old->bag_id !== $bag->id || ! hash_equals($old->fingerprint, $fingerprint)) {
                    Pricing::fail('attempt_id', 'This retry key belongs to different checkout details.');
                }

                return $old->order_id;
            }
            if ($bag->active_order_id) {
                Pricing::fail('bag', 'Resume your existing order before starting another checkout.');
            }
            if ($v['revision'] !== $bag->revision) {
                Pricing::fail('revision', 'Refresh your bag before checkout.');
            }
            $quote = app(Pricing::class)->quote($bag, $v);
            if (! hash_equals($quote['quote_hash'], $v['quote_hash'])) {
                Pricing::fail('quote_hash', 'Price or checkout details changed. Review the updated total before paying.');
            }
            $s = $quote['snapshot'];
            $id = (string) Str::uuid();
            DB::table('commerce_orders')->insert(['id' => $id, 'bag_id' => $bag->id, 'reference' => 'EV-'.strtoupper(bin2hex(random_bytes(6))), 'order_state' => 'awaiting_payment', 'payment_method' => $wise ? 'wise_bank_transfer' : 'stripe_sandbox', 'payment_state' => $wise ? 'awaiting_bank_transfer' : ($manual ? 'awaiting_payment_link' : 'creating'), 'fulfilment_state' => 'unfulfilled', 'total_minor' => $s['total_minor'], 'currency' => 'GBP', 'reservation_expires_at' => ($wise ? now()->addHours(config('commerce.wise.window_hours')) : now()->addSeconds(config('commerce.session_seconds') + 300)), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('commerce_order_snapshots')->insert(['order_id' => $id, 'payload' => json_encode($s), 'created_at' => now()]);
            foreach ($s['items'] as $line) {
                DB::table('commerce_order_items')->insert(array_intersect_key($line, array_flip(['variant_id', 'product_id', 'sku', 'title', 'colour', 'size', 'quantity', 'unit_minor', 'line_minor', 'currency'])) + ['order_id' => $id, 'created_at' => now()]);
            }
            foreach (['shipping' => 'shipping_address', 'billing' => 'billing_address'] as $kind => $key) {
                DB::table('commerce_order_addresses')->insert($s[$key] + ['order_id' => $id, 'kind' => $kind, 'created_at' => now()]);
            }
            DB::table('commerce_checkout_attempts')->insert(['id' => $v['attempt_id'], 'bag_id' => $bag->id, 'order_id' => $id, 'fingerprint' => $fingerprint, 'created_at' => now()]);
            $this->reserve($id, $s);
            DB::table('commerce_payments')->insert(['order_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            if ($wise) {
                DB::table('wise_bank_transfers')->insert(['order_id' => $id, 'instructions' => Crypt::encryptString(json_encode(app(WiseBankTransfer::class)->snapshot())), 'created_at' => now()]);
            }
            DB::table('commerce_bags')->where('id', $bag->id)->update(['active_order_id' => $id, 'updated_at' => now()]);

            return $id;
        }, 5);
        if (! $manual && ! $wise) {
            $this->ensureSession($id);
        }

        return $this->view($id);
    }

    public function reserve(string $id, array $s): void
    {
        foreach ($s['items'] as $line) {
            // Lock the same variant rows used by staff adjustments, then conditionally reserve each balance.
            DB::table('catalogue_variants')->where('id', $line['variant_id'])->lockForUpdate()->first();
            $remaining = $line['quantity'];
            $balances = DB::table('stock_balances as b')->join('stock_locations as l', 'l.id', '=', 'b.location_id')->where('l.active', true)->where('b.variant_id', $line['variant_id'])->orderBy('b.id')->select('b.*')->lockForUpdate()->get();
            foreach ($balances as $b) {
                $take = min($remaining, $b->on_hand - $b->reserved);
                if ($take < 1) {
                    continue;
                }
                $changed = DB::table('stock_balances')->where('id', $b->id)->whereRaw('on_hand - reserved >= ?', [$take])->increment('reserved', $take);
                if ($changed !== 1) {
                    Pricing::fail('stock', 'Stock changed. Review your bag.');
                }
                DB::table('commerce_reservations')->insert(['order_id' => $id, 'variant_id' => $line['variant_id'], 'balance_id' => $b->id, 'quantity' => $take, 'state' => 'reserved', 'created_at' => now(), 'updated_at' => now()]);
                $this->ledger($id, $b->id, 'reserve', 0, $take);
                $remaining -= $take;
                if (! $remaining) {
                    break;
                }
            }if ($remaining) {
                Pricing::fail('stock', 'Another checkout reserved this piece. Review your bag.');
            }
        }
    }

    public function ensureSession(string $id): void
    {
        if (DB::table('commerce_orders')->where('id', $id)->value('payment_method') !== 'stripe_sandbox') {
            return;
        }
        if (! app(StripeSandbox::class)->configured()) {
            return;
        }
        $p = DB::table('commerce_payments')->where('order_id', $id)->first();
        $o = DB::table('commerce_orders')->find($id);
        if ($p->session_id || in_array($o->payment_state, ['paid', 'expired', 'failed', 'review'])) {
            return;
        }
        if ($p->creation_started_at && now()->diffInSeconds($p->creation_started_at, true) > 23 * 3600) {
            $this->review($id, 'creation_retry_window_exceeded');

            return;
        }
        // Freeze the complete provider request before any network call. Config edits must not alter retries.
        $a = DB::table('commerce_checkout_attempts')->where('order_id', $id)->first();
        if (! $p->creation_started_at) {
            $started = now();
            $parameters = app(StripeSandbox::class)->parameters($o, $this->snapshot($id), $a->id, $started->timestamp + config('commerce.session_seconds'));
            DB::table('commerce_payments')->where('order_id', $id)->whereNull('creation_started_at')->update(['creation_started_at' => $started, 'request_payload' => json_encode($parameters), 'updated_at' => $started]);
        }
        $p = DB::table('commerce_payments')->where('order_id', $id)->first();
        try {
            $session = app(StripeSandbox::class)->create(json_decode($p->request_payload, true), $a->id);
            $this->validateSession($o, $session, $a->id);
            $url = $session['url'] ?? null;
            if (($session['status'] ?? '') === 'open' && (! is_string($url) || parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== 'checkout.stripe.com')) {
                throw new \RuntimeException('Invalid hosted Checkout URL.');
            }
            DB::transaction(function () use ($id, $session, $url) {
                $o = DB::table('commerce_orders')->where('id', $id)->lockForUpdate()->first();
                $p = DB::table('commerce_payments')->where('order_id', $id)->first();
                if ($p->session_id && $p->session_id !== $session['id']) {
                    throw new \RuntimeException('Conflicting provider session.');
                }DB::table('commerce_payments')->where('order_id', $id)->update(['session_id' => $session['id'], 'checkout_url' => $url, 'last_error' => null, 'updated_at' => now()]);
                if ($o->payment_state === 'creating') {
                    DB::table('commerce_orders')->where('id', $id)->update(['payment_state' => 'open', 'updated_at' => now()]);
                }
            }, 5);
        } catch (\Throwable $e) {
            DB::table('commerce_payments')->where('order_id', $id)->update(['last_error' => 'provider_unavailable_or_response_invalid', 'updated_at' => now()]);
        }
    }

    public function snapshot(string $id): array
    {
        return json_decode(DB::table('commerce_order_snapshots')->where('order_id', $id)->value('payload'), true);
    }

    public function view(string $id): array
    {
        $o = DB::table('commerce_orders')->find($id);
        abort_unless($o, 404);
        $p = DB::table('commerce_payments')->where('order_id', $id)->first();

        return ['created_at' => $o->created_at, 'payment_method' => $o->payment_method, 'payment_instructions' => app(WiseBankTransfer::class)->instructions($o), 'id' => $o->id, 'reference' => $o->reference, 'order_state' => $o->order_state, 'payment_state' => $o->payment_state, 'fulfilment_state' => $o->fulfilment_state, 'total_minor' => $o->total_minor, 'currency' => $o->currency, 'paid_at' => $o->paid_at, 'reservation_expires_at' => $o->reservation_expires_at, 'snapshot' => $this->snapshot($id), 'checkout_url' => in_array($o->payment_state, ['open', 'pending']) ? $p->checkout_url : null, 'recoverable' => $o->payment_state === 'creating', 'provider_issue' => $p->last_error !== null, 'mail_status' => DB::table('commerce_mail_outbox')->where('order_id', $id)->value('delivered_at') ? 'saved_private_sandbox_copy' : (DB::table('commerce_mail_outbox')->where('order_id', $id)->exists() ? 'queued' : 'not_queued')];
    }

    private function validateSession(object $o, array $s, string $attempt): void
    {
        if (! is_string($s['id'] ?? null) || ! preg_match('/^cs_test_[A-Za-z0-9_]+$/', $s['id']) || ($s['livemode'] ?? true) !== false || ($s['mode'] ?? '') !== 'payment' || ($s['client_reference_id'] ?? '') !== $o->id || ($s['metadata']['order_id'] ?? '') !== $o->id || ($s['metadata']['checkout_attempt'] ?? '') !== $attempt || ($s['currency'] ?? '') !== 'gbp' || ($s['amount_total'] ?? null) !== $o->total_minor) {
            throw new \RuntimeException('Provider evidence does not match the order.');
        }
    }

    public function reconcile(string $id, bool $expire = false, ?array $event = null, ?string $hash = null): void
    {
        if (DB::table('commerce_orders')->where('id', $id)->value('payment_method') === 'wise_bank_transfer') {
            if ($expire) {
                app(WiseBankTransfer::class)->expire($id);
            }

            return;
        }
        $p = DB::table('commerce_payments')->where('order_id', $id)->first();
        if (! $p->session_id) {
            $this->ensureSession($id);
            $p = DB::table('commerce_payments')->where('order_id', $id)->first();
            if (! $p->session_id) {
                throw new \RuntimeException('Provider creation unresolved; stock retained.');
            }
        }
        $o = DB::table('commerce_orders')->find($id);
        $a = DB::table('commerce_checkout_attempts')->where('order_id', $id)->first();
        $gateway = app(StripeSandbox::class);
        $s = $gateway->get($p->session_id);
        try {
            $this->validateSession($o, $s, $a->id);
            if ($s['id'] !== $p->session_id) {
                throw new \RuntimeException('Provider session mismatch.');
            }
        } catch (\Throwable $e) {
            $this->review($id, 'provider_evidence_mismatch');
            throw $e;
        }
        if ($expire && ($s['status'] ?? '') === 'open' && now()->gte($o->reservation_expires_at)) {
            $s = $gateway->expire($p->session_id);
            $this->validateSession($o, $s, $a->id);
            if ($s['id'] !== $p->session_id) {
                throw new \RuntimeException('Provider session mismatch.');
            }
        }
        DB::transaction(function () use ($id, $s, $event, $hash) {
            $o = DB::table('commerce_orders')->where('id', $id)->lockForUpdate()->first();
            if ($event) {
                if (DB::table('commerce_webhook_events')->where('id', $event['id'])->exists()) {
                    return;
                }DB::table('commerce_webhook_events')->insert(['id' => $event['id'], 'type' => $event['type'], 'session_id' => $s['id'], 'payload_hash' => $hash, 'created_at' => now()]);
            }
            if ($o->payment_state === 'paid') {
                return;
            }
            if (($s['payment_status'] ?? '') === 'paid' && ($s['status'] ?? '') === 'complete') {
                if (in_array($o->payment_state, ['expired', 'failed', 'review'])) {
                    $this->review($id, 'paid_evidence_after_terminal_state');

                    return;
                }
                $this->settle($o, true);
                DB::table('commerce_orders')->where('id', $id)->update(['payment_state' => 'paid', 'order_state' => 'confirmed', 'paid_at' => now(), 'updated_at' => now()]);
                DB::table('commerce_bags')->where('id', $o->bag_id)->where('active_order_id', $id)->update(['active_order_id' => null, 'items' => '{}', 'revision' => DB::raw('revision+1'), 'updated_at' => now()]);
                DB::table('commerce_mail_outbox')->insertOrIgnore(['order_id' => $id, 'created_at' => now()]);
            } elseif (($s['status'] ?? '') === 'expired') {
                if (! in_array($o->payment_state, ['expired', 'failed', 'review'])) {
                    $this->settle($o, false);
                    DB::table('commerce_orders')->where('id', $id)->update(['payment_state' => 'expired', 'order_state' => 'expired', 'updated_at' => now()]);
                    DB::table('commerce_bags')->where('id', $o->bag_id)->where('active_order_id', $id)->update(['active_order_id' => null, 'updated_at' => now()]);
                }
            } elseif (($event['type'] ?? '') === 'checkout.session.async_payment_failed' && ($s['status'] ?? '') === 'complete' && ($s['payment_status'] ?? '') === 'unpaid') {
                if (! in_array($o->payment_state, ['expired', 'failed', 'review'])) {
                    $this->settle($o, false);
                    DB::table('commerce_orders')->where('id', $id)->update(['payment_state' => 'failed', 'order_state' => 'payment_failed', 'updated_at' => now()]);
                    DB::table('commerce_bags')->where('id', $o->bag_id)->where('active_order_id', $id)->update(['active_order_id' => null, 'updated_at' => now()]);
                }
            } elseif (($s['status'] ?? '') === 'complete' && ($s['payment_status'] ?? '') === 'unpaid') {
                // Delayed/pending evidence never releases stock or confirms an order.
                if (! in_array($o->payment_state, ['expired', 'failed', 'review'])) {
                    DB::table('commerce_orders')->where('id', $id)->update(['payment_state' => 'pending', 'updated_at' => now()]);
                }
            }
            DB::table('commerce_payments')->where('order_id', $id)->update(['last_error' => null, 'updated_at' => now()]);
        }, 5);
    }

    public function expireUnattemptedManual(string $id): void
    {
        DB::transaction(function () use ($id) {
            $o = DB::table('commerce_orders')->where('id', $id)->lockForUpdate()->first();
            $p = DB::table('commerce_payments')->where('order_id', $id)->first();
            if (! $o || ! DB::table('manual_order_attempts')->where('order_id', $id)->exists() || ! in_array($o->payment_state, ['awaiting_payment_link', 'creating']) || $p->creation_started_at || now()->lt($o->reservation_expires_at)) {
                return;
            }
            $this->settle($o, false);
            DB::table('commerce_orders')->where('id', $id)->update(['payment_state' => 'expired', 'order_state' => 'expired', 'updated_at' => now()]);
            DB::table('commerce_bags')->where('id', $o->bag_id)->where('active_order_id', $id)->update(['active_order_id' => null, 'updated_at' => now()]);
        }, 5);
    }

    public function settle(object $o, bool $paid): void
    {
        $rows = DB::table('commerce_reservations')->where('order_id', $o->id)->orderBy('balance_id')->lockForUpdate()->get();
        if (! $rows->count()) {
            throw new \RuntimeException('Missing reservations.');
        }
        foreach ($rows as $r) {
            if ($r->state !== 'reserved') {
                throw new \RuntimeException('Reservation is already terminal.');
            }$q = DB::table('stock_balances')->where('id', $r->balance_id)->where('reserved', '>=', $r->quantity);
            if ($paid) {
                $q->where('on_hand', '>=', $r->quantity);
            }
            $changed = $q->update(['reserved' => DB::raw('reserved-'.$r->quantity)] + ($paid ? ['on_hand' => DB::raw('on_hand-'.$r->quantity)] : []));
            if ($changed !== 1) {
                throw new \RuntimeException('Inventory invariant violated.');
            }
            DB::table('commerce_reservations')->where('id', $r->id)->update(['state' => $paid ? 'committed' : 'released', 'updated_at' => now()]);
            $this->ledger($o->id, $r->balance_id, $paid ? 'commit' : 'release', $paid ? -$r->quantity : 0, -$r->quantity);
        }
    }

    private function ledger(string $order, int $balance, string $kind, int $on, int $reserved): void
    {
        $b = DB::table('stock_balances')->find($balance);
        DB::table('commerce_stock_events')->insert(['order_id' => $order, 'balance_id' => $balance, 'kind' => $kind, 'on_hand_delta' => $on, 'reserved_delta' => $reserved, 'on_hand_after' => $b->on_hand, 'reserved_after' => $b->reserved, 'created_at' => now()]);
    }

    private function review(string $id, string $reason): void
    {
        DB::transaction(function () use ($id, $reason) {
            $o = DB::table('commerce_orders')->where('id', $id)->lockForUpdate()->first();
            if ($o->payment_state === 'paid') {
                return;
            }DB::table('commerce_orders')->where('id', $id)->update(['payment_state' => 'review', 'order_state' => 'review', 'fulfilment_state' => 'on_hold', 'updated_at' => now()]);
            DB::table('commerce_payments')->where('order_id', $id)->update(['last_error' => $reason, 'updated_at' => now()]);
        }, 5);
    }
}
