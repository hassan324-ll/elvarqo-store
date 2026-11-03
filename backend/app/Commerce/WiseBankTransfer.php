<?php

namespace App\Commerce;

use App\Security\StaffAudit;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class WiseBankTransfer
{
    public function configured(): bool
    {
        $v = config('commerce.wise');

        return (bool) $v['enabled'] && is_string($v['account_holder']) && trim($v['account_holder']) !== ''
            && mb_strlen($v['account_holder']) <= 160 && ! preg_match('/[\r\n\x00]/', $v['account_holder'])
            && is_string($v['sort_code']) && is_string($v['account_number'])
            && preg_match('/^\d{6}$/', preg_replace('/[ -]/', '', $v['sort_code']))
            && preg_match('/^\d{8}$/', $v['account_number'])
            && $v['window_hours'] >= 1 && $v['window_hours'] <= 168;
    }

    public function ready(): void
    {
        abort_unless($this->configured(), 503, 'Wise bank-transfer checkout is not configured. Your bag is saved.');
    }

    public function snapshot(): array
    {
        $this->ready();
        $sort = preg_replace('/[ -]/', '', config('commerce.wise.sort_code'));

        return ['provider' => 'Wise', 'account_holder' => trim(config('commerce.wise.account_holder')), 'sort_code' => implode('-', str_split($sort, 2)), 'account_number' => config('commerce.wise.account_number'), 'currency' => 'GBP'];
    }

    public function instructions(object $order): ?array
    {
        if ($order->payment_method !== 'wise_bank_transfer' || $order->payment_state !== 'awaiting_bank_transfer' || now()->gte($order->reservation_expires_at)) {
            return null;
        }
        $row = DB::table('wise_bank_transfers')->where('order_id', $order->id)->first();
        if (! $row) {
            return null;
        }

        return json_decode(Crypt::decryptString($row->instructions), true) + ['payment_reference' => $order->reference, 'amount_minor' => $order->total_minor, 'pay_before' => $order->reservation_expires_at];
    }

    public function confirm(string $id, array $v, int $actor): void
    {
        DB::transaction(function () use ($id, $v, $actor) {
            $o = DB::table('commerce_orders')->where('id', $id)->lockForUpdate()->first();
            abort_unless($o, 404);
            $receipt = DB::table('wise_bank_transfers')->where('order_id', $id)->lockForUpdate()->first();
            abort_unless($o->payment_method === 'wise_bank_transfer' && $receipt, 409, 'This is not a Wise bank-transfer order.');
            $v['receipt_reference'] = strtoupper(trim($v['receipt_reference']));
            $v['payment_reference'] = strtoupper(trim($v['payment_reference']));
            $fp = hash('sha256', json_encode([$id, $v, $actor]));
            $old = DB::table('wise_bank_transfers')->where('operation_id', $v['operation_id'])->first();
            if ($old) {
                abort_unless($old->order_id === $id && hash_equals($old->fingerprint, $fp), 409, 'This confirmation key belongs to another request.');

                return;
            }
            if ($receipt->confirmed_at || $o->payment_state !== 'awaiting_bank_transfer' || $o->order_state !== 'awaiting_payment') {
                Pricing::fail('order', 'Only an awaiting Wise payment can be confirmed.');
            }
            if (now()->gte($o->reservation_expires_at)) {
                Pricing::fail('order', 'The payment deadline passed. Review the received funds and stock separately; do not confirm an expired reservation.');
            }
            if ((int) $v['amount_minor'] !== $o->total_minor || $v['currency'] !== $o->currency || $v['payment_reference'] !== $o->reference) {
                Pricing::fail('payment', 'Received amount, GBP currency and payment reference must exactly match this order. Review mismatches separately.');
            }
            $hash = hash('sha256', $v['receipt_reference']);
            if (DB::table('wise_bank_transfers')->where('receipt_hash', $hash)->exists()) {
                Pricing::fail('receipt_reference', 'This Wise transaction has already been used for an order.');
            }
            app(Checkout::class)->settle($o, true);
            DB::table('wise_bank_transfers')->where('order_id', $id)->update(['operation_id' => $v['operation_id'], 'fingerprint' => $fp, 'receipt_hash' => $hash, 'receipt_reference' => Crypt::encryptString($v['receipt_reference']), 'confirmed_by' => $actor, 'confirmed_at' => now()]);
            DB::table('commerce_orders')->where('id', $id)->update(['payment_state' => 'paid', 'order_state' => 'confirmed', 'paid_at' => now(), 'updated_at' => now()]);
            DB::table('commerce_bags')->where('id', $o->bag_id)->where('active_order_id', $id)->update(['active_order_id' => null, 'items' => '{}', 'revision' => DB::raw('revision+1'), 'updated_at' => now()]);
            DB::table('commerce_mail_outbox')->insertOrIgnore(['order_id' => $id, 'created_at' => now()]);
            StaffAudit::record('payment.wise_receipt_confirmed', $actor, 'order:'.$id);
        }, 5);
    }

    public function confirmation(string $id): ?array
    {
        $row = DB::table('wise_bank_transfers as w')->leftJoin('staff_users as s', 's.id', '=', 'w.confirmed_by')->where('w.order_id', $id)->whereNotNull('w.confirmed_at')->first(['w.confirmed_at', 'w.receipt_reference', 's.name']);
        if (! $row) {
            return null;
        }

        return ['confirmed_at' => $row->confirmed_at, 'confirmed_by' => $row->name, 'receipt_reference' => auth('staff')->user()?->canAccess('finance.confirm') ? Crypt::decryptString($row->receipt_reference) : null];
    }

    public function expire(string $id): void
    {
        DB::transaction(function () use ($id) {
            $o = DB::table('commerce_orders')->where('id', $id)->lockForUpdate()->first();
            if (! $o || $o->payment_method !== 'wise_bank_transfer' || $o->payment_state !== 'awaiting_bank_transfer' || now()->lt($o->reservation_expires_at)) {
                return;
            }
            app(Checkout::class)->settle($o, false);
            DB::table('commerce_orders')->where('id', $id)->update(['payment_state' => 'expired', 'order_state' => 'expired', 'updated_at' => now()]);
            DB::table('commerce_bags')->where('id', $o->bag_id)->where('active_order_id', $id)->update(['active_order_id' => null, 'updated_at' => now()]);
        }, 5);
    }
}
