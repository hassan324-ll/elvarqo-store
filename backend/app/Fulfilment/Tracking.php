<?php

namespace App\Fulfilment;

use App\Commerce\Pricing;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Tracking
{
    public function issue(string $order, string $purpose): void
    {
        $token = bin2hex(random_bytes(32));
        $id = (string) Str::uuid();
        DB::table('order_access_links')->insert(['id' => $id, 'order_id' => $order, 'purpose' => $purpose, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(30), 'created_at' => now()]);
        $route = $purpose === 'payment' ? 'pay-order' : 'track';
        Notifications::queue($order, $purpose.'_link', 'link:'.$id, ['url' => rtrim(config('commerce.storefront_origin'), '/').'/'.$route.'#token='.$token]);
    }

    public function request(array $v): void
    {
        DB::transaction(function () use ($v) {
            $order = DB::table('commerce_orders')->where('reference', strtoupper(trim($v['reference'])))->first();
            if (! $order) {
                return;
            }$s = json_decode(DB::table('commerce_order_snapshots')->where('order_id', $order->id)->value('payload'), true);
            if (! hash_equals(strtolower($s['contact']['email']), strtolower(trim($v['email'])))) {
                return;
            }
            // Bound link issuance per order in addition to public rate limits; always return the same response.
            $count = DB::table('order_access_links')->where('order_id', $order->id)->where('purpose', 'tracking')->where('created_at', '>', now()->subHour())->count();
            if ($count < 5) {
                $this->issue($order->id, 'tracking');
            }
        }, 5);
    }

    public function redeem(string $token, string $purpose): array
    {
        return DB::transaction(function () use ($token, $purpose) {
            $link = DB::table('order_access_links')->where('token_hash', hash('sha256', $token))->where('purpose', $purpose)->lockForUpdate()->first();
            abort_unless($link && ! $link->used_at && now()->lt($link->expires_at), 401);
            $o = DB::table('commerce_orders')->where('id', $link->order_id)->lockForUpdate()->first();
            if ($purpose === 'payment') {
                $payment = DB::table('commerce_payments')->where('order_id', $o->id)->first();
                if (! $payment->creation_started_at && now()->gte($o->reservation_expires_at)) {
                    Pricing::fail('order', 'This manual payment reservation expired. Ask the store to review a fresh order.');
                }
                if (! DB::table('manual_order_attempts')->where('order_id', $o->id)->exists() || ! in_array($o->payment_state, ['awaiting_payment_link', 'awaiting_bank_transfer', 'creating', 'open'])) {
                    Pricing::fail('order', 'This payment invitation is no longer eligible.');
                }$cap = bin2hex(random_bytes(32));
                DB::table('commerce_bags')->where('id', $o->bag_id)->update(['token_hash' => hash('sha256', $cap), 'expires_at' => now()->addDays(30), 'updated_at' => now()]);
                if ($o->payment_state === 'awaiting_payment_link') {
                    DB::table('commerce_orders')->where('id', $o->id)->update(['payment_state' => 'creating', 'updated_at' => now()]);
                }
            } else {
                $cap = bin2hex(random_bytes(32));
                DB::table('order_tracking_sessions')->insert(['token_hash' => hash('sha256', $cap), 'order_id' => $o->id, 'expires_at' => now()->addDay(), 'created_at' => now()]);
            }
            DB::table('order_access_links')->where('id', $link->id)->update(['used_at' => now()]);

            return ['token' => $cap, 'order_id' => $o->id];
        }, 5);
    }

    public function owned(Request $r): object
    {
        $token = $r->bearerToken();
        abort_unless($token && preg_match('/^[a-f0-9]{64}$/', $token), 401);
        $session = DB::table('order_tracking_sessions')->where('token_hash', hash('sha256', $token))->where('expires_at', '>', now())->first();
        abort_unless($session, 401);

        return DB::table('commerce_orders')->find($session->order_id);
    }

    public function view(Request $r): array
    {
        return $this->summary($this->owned($r));
    }

    public function summary(object $o): array
    {
        $shipments = DB::table('order_fulfilments')->where('order_id', $o->id)->whereNotIn('state', ['draft', 'failed', 'unknown', 'booking', 'cancelled'])->orderBy('created_at')->get()->map(fn ($f) => ['id' => $f->id, 'state' => $f->state, 'tracking_number' => $f->tracking_number, 'sandbox' => true, 'dispatched_at' => $f->dispatched_at, 'items' => DB::table('fulfilment_items as f')->join('commerce_order_items as i', 'i.id', '=', 'f.order_item_id')->where('f.fulfilment_id', $f->id)->select('i.title', 'i.colour', 'i.size', 'f.quantity')->get(), 'events' => DB::table('courier_tracking_events')->where('fulfilment_id', $f->id)->where('applied', true)->orderBy('occurred_at')->get(['state', 'occurred_at'])]);

        return ['reference' => $o->reference, 'order_state' => $o->order_state, 'payment_state' => $o->payment_state, 'fulfilment_state' => $o->fulfilment_state, 'shipments' => $shipments, 'sandbox' => true, 'support' => DB::table('order_support_requests')->where('order_id', $o->id)->orderByDesc('created_at')->get(['id', 'subject', 'state', 'created_at'])];
    }

    public function event(string $body, string $header): void
    {
        $secret = config('fulfilment.webhook_secret');
        abort_unless(is_string($secret) && strlen($secret) >= 32, 503);
        abort_if(strlen($body) > 32768, 413);
        $parts = [];
        foreach (explode(',', $header) as $part) {
            $p = explode('=', trim($part), 2);
            if (count($p) === 2) {
                $parts[$p[0]] = $p[1];
            }
        }$t = $parts['t'] ?? '';
        abort_unless(ctype_digit($t) && abs(time() - (int) $t) <= 300 && hash_equals(hash_hmac('sha256', $t.'.'.$body, $secret), $parts['v1'] ?? ''), 400);
        try {
            $e = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            abort(400);
        }$v = validator($e, ['id' => 'required|string|max:160|regex:/^[A-Za-z0-9_-]+$/', 'provider_reference' => 'required|string|max:100', 'tracking_number' => 'required|string|max:100', 'state' => 'required|in:in_transit,out_for_delivery,delivered,exception', 'occurred_at' => 'required|date'])->validate();
        $when = Carbon::parse($v['occurred_at'])->utc();
        abort_unless($when->lte(now()->addMinutes(5)), 422);
        DB::transaction(function () use ($v, $when, $body) {
            $old = DB::table('courier_tracking_events')->find($v['id']);
            if ($old) {
                abort_unless(hash_equals($old->payload_hash, hash('sha256', $body)), 409);

                return;
            }$f = DB::table('order_fulfilments')->where('provider_reference', $v['provider_reference'])->where('tracking_number', $v['tracking_number'])->first();
            abort_unless($f, 404);
            DB::table('commerce_orders')->where('id', $f->order_id)->lockForUpdate()->first();
            $f = DB::table('order_fulfilments')->where('id', $f->id)->lockForUpdate()->first();
            $rank = ['in_transit' => 20, 'out_for_delivery' => 30, 'delivered' => 40, 'exception' => max(10, $f->progress_rank)][$v['state']];
            $apply = $when->gte($f->created_at) && ! in_array($f->state, ['cancelled', 'draft', 'failed', 'unknown', 'booking', 'delivered']) && (! $f->last_event_at || $when->gte($f->last_event_at)) && $rank >= $f->progress_rank;
            DB::table('courier_tracking_events')->insert(['id' => $v['id'], 'fulfilment_id' => $f->id, 'state' => $v['state'], 'payload_hash' => hash('sha256', $body), 'applied' => $apply, 'occurred_at' => $when, 'created_at' => now()]);
            if (! $apply) {
                return;
            }
            DB::table('order_fulfilments')->where('id', $f->id)->update(['state' => $v['state'], 'progress_rank' => $rank, 'last_event_at' => $when, 'dispatched_at' => $f->dispatched_at ?? $when, 'updated_at' => now()]);
            if (! $f->dispatched_at) {
                Notifications::queue($f->order_id, 'dispatch', 'dispatch:'.$f->id, [], $f->id);
            }Notifications::queue($f->order_id, 'tracking_update', 'tracking:'.$v['id'], ['state' => $v['state']], $f->id);
            app(Shipments::class)->aggregate($f->order_id);
        }, 5);
    }
}
