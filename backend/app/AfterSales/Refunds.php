<?php

namespace App\AfterSales;

use App\Commerce\Pricing;
use App\Security\StaffAudit;
use Illuminate\Support\Facades\DB;

class Refunds
{
    public function ready(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 503, 'Refund sandbox is disabled outside local/testing.');
    }

    public function remaining(object $r): int
    {
        if ($r->state !== 'accepted') {
            return 0;
        }
        $exchange = (int) DB::table('exchange_links')->where('return_id', $r->id)->where('state', '!=', 'expired')->sum('credit_minor');
        $held = (int) DB::table('refund_attempts')->where('return_id', $r->id)->where('state', '!=', 'sandbox_failed')->sum('amount_minor');

        return max(0, $r->credit_minor - $exchange - $held);
    }

    public function start(string $id, array $v, int $actor): array
    {
        $this->ready();
        $attempt = DB::transaction(function () use ($id, $v, $actor) {
            $r = app(Returns::class)->locked($id);
            $fp = hash('sha256', json_encode([$id, $v, $actor]));
            $old = DB::table('refund_attempts')->find($v['operation_id']);
            if ($old) {
                abort_unless(hash_equals($old->fingerprint, $fp), 409);

                return $old->id;
            }
            if ($r->resolution !== 'refund' && ! DB::table('exchange_links')->where('return_id', $id)->where('state', 'issued')->exists()) {
                Pricing::fail('resolution', 'Resolve or issue the exchange before refund review.');
            }
            $o = DB::table('commerce_orders')->find($r->order_id);
            abort_if($o->payment_method === 'wise_bank_transfer', 503, 'Wise bank-transfer refunds are not automated. A sandbox refund cannot return bank funds.');
            $all = (int) DB::table('refund_attempts')->where('order_id', $o->id)->where('state', '!=', 'sandbox_failed')->sum('amount_minor');
            $funded = (int) DB::table('exchange_links as e')->join('after_sales_returns as r', 'r.id', '=', 'e.return_id')->where('r.order_id', $o->id)->where('e.state', '!=', 'expired')->sum('e.credit_minor');
            if ($o->payment_state !== 'paid' || $v['amount_minor'] > $this->remaining($r) || $o->total_minor < $v['amount_minor'] + $all + $funded) {
                Pricing::fail('amount_minor', 'Amount exceeds the remaining inspected merchandise or order balance.');
            }
            DB::table('refund_attempts')->insert(['id' => $v['operation_id'], 'return_id' => $id, 'order_id' => $o->id, 'staff_user_id' => $actor, 'amount_minor' => $v['amount_minor'], 'currency' => 'GBP', 'reason' => trim($v['reason']), 'scenario' => $v['scenario'], 'fingerprint' => $fp, 'state' => 'sandbox_unknown', 'created_at' => now(), 'updated_at' => now()]);
            app(Returns::class)->event($id, 'refund_requested', ['attempt_id' => $v['operation_id'], 'amount_minor' => $v['amount_minor']]);
            StaffAudit::record('refund.sandbox_requested', $actor, 'return:'.$id);

            return $v['operation_id'];
        }, 5);
        $this->process($attempt);

        return (array) DB::table('refund_attempts')->find($attempt);
    }

    public function process(string $id): void
    {
        $this->ready();
        $timeout = false;
        DB::transaction(function () use ($id, &$timeout) {
            $a = DB::table('refund_attempts')->find($id);
            abort_unless($a, 404);
            app(Returns::class)->locked($a->return_id);
            $a = DB::table('refund_attempts')->where('id', $id)->lockForUpdate()->first();
            if (in_array($a->state, ['sandbox_succeeded', 'sandbox_failed'])) {
                return;
            }
            $receipt = DB::table('sandbox_refund_receipts')->where('attempt_id', $id)->first();
            if (! $receipt) {
                $state = match ($a->scenario) {
                    'pending' => 'pending','fail' => 'failed',default => 'succeeded'
                };
                DB::table('sandbox_refund_receipts')->insert(['attempt_id' => $id, 'order_id' => $a->order_id, 'amount_minor' => $a->amount_minor, 'currency' => $a->currency, 'state' => $state, 'provider_reference' => 'local_refund_'.$id, 'created_at' => now(), 'updated_at' => now()]);
                $receipt = DB::table('sandbox_refund_receipts')->where('attempt_id', $id)->first();
                $timeout = $a->scenario === 'timeout_after_once';
            }
            if ($timeout) {
                DB::table('refund_attempts')->where('id', $id)->update(['state' => 'sandbox_unknown', 'last_error' => 'Response lost. Retry/reconcile the same attempt; credit remains held.', 'updated_at' => now()]);

                return;
            }
            if ($receipt->order_id !== $a->order_id || $receipt->amount_minor !== $a->amount_minor || $receipt->currency !== $a->currency || $receipt->provider_reference !== 'local_refund_'.$id || ! in_array($receipt->state, ['succeeded', 'failed', 'pending'])) {
                DB::table('refund_attempts')->where('id', $id)->update(['last_error' => 'Provider evidence mismatch; credit held for review.', 'updated_at' => now()]);

                return;
            }
            $state = 'sandbox_'.$receipt->state;
            DB::table('refund_attempts')->where('id', $id)->update(['state' => $state, 'last_error' => $state === 'sandbox_failed' ? 'Local sandbox rejected this attempt; no real money moved.' : null, 'updated_at' => now()]);
            if ($state !== $a->state) {
                app(Returns::class)->event($a->return_id, 'refund_'.$state, ['attempt_id' => $id, 'amount_minor' => $a->amount_minor]);
            }
        }, 5);
    }
}
