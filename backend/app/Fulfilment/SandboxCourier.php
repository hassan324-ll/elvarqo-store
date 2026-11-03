<?php

namespace App\Fulfilment;

use Illuminate\Support\Facades\DB;

class SandboxCourier
{
    public function book(object $shipment): object
    {
        $quote = DB::table('shipment_quotes')->find($shipment->quote_id);
        $count = DB::table('courier_booking_attempts')->where('fulfilment_id', $shipment->id)->where('action', 'book')->count();
        $scenario = config('fulfilment.scenario');
        if ($scenario === 'reject') {
            throw new \DomainException('Sandbox provider rejected this parcel. No label created.');
        }
        if ($scenario === 'timeout_before_once' && $count === 0) {
            throw new \RuntimeException('Sandbox timeout before receipt. Retry the same booking.');
        }
        $receipt = DB::transaction(function () use ($shipment, $quote) {
            DB::table('order_fulfilments')->where('id', $shipment->id)->lockForUpdate()->first();
            $old = DB::table('sandbox_courier_receipts')->where('fulfilment_id', $shipment->id)->first();
            if ($old) {
                if ($old->fingerprint !== $quote->fingerprint || $old->state !== 'booked') {
                    throw new \DomainException('Sandbox receipt is void or mismatched.');
                }

return $old;
            }
            $reference = 'SBX-'.strtoupper(str_replace('-', '', $shipment->id));
            DB::table('sandbox_courier_receipts')->insert(['fulfilment_id' => $shipment->id, 'fingerprint' => $quote->fingerprint, 'reference' => $reference, 'tracking_number' => 'TEST'.strtoupper(substr(str_replace('-', '', $shipment->id), 0, 20)), 'state' => 'booked', 'created_at' => now(), 'updated_at' => now()]);

            return DB::table('sandbox_courier_receipts')->where('fulfilment_id', $shipment->id)->first();
        }, 5);
        if ($scenario === 'timeout_after_once' && $count === 0) {
            throw new \RuntimeException('Sandbox timeout after receipt. Outcome uncertain; retry this booking only.');
        }

return $receipt;
    }

    public function void(object $shipment): void
    {
        DB::transaction(function () use ($shipment) {
            $r = DB::table('sandbox_courier_receipts')->where('fulfilment_id', $shipment->id)->lockForUpdate()->first();
            if ($r && $r->state === 'booked') {
                DB::table('sandbox_courier_receipts')->where('fulfilment_id', $shipment->id)->update(['state' => 'void', 'updated_at' => now()]);
            }
        }, 5);
    }
}
