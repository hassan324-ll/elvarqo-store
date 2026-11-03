<?php

namespace App\Jobs;

use App\Commerce\Checkout;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeliverSandboxOrderMail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $orderId) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $out = DB::table('commerce_mail_outbox')->where('order_id', $this->orderId)->lockForUpdate()->first();
            if (! $out || $out->delivered_at) {
                return;
            }
            $order = app(Checkout::class)->view($this->orderId);
            if ($order['payment_state'] !== 'paid') {
                return;
            }
            $s = $order['snapshot'];
            $wise = $order['payment_method'] === 'wise_bank_transfer';
            $text = 'To: '.$s['contact']['email']."\r\nSubject: ELVARQO ".($wise ? 'order ' : 'sandbox order ').$order['reference']."\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n".($wise ? "Wise bank-transfer receipt confirmed by store staff.\n" : "Sandbox payment verified.\n");
            foreach ($s['items'] as $l) {
                $text .= $l['title'].' / '.$l['colour'].' / '.$l['size'].' / Qty '.$l['quantity']."\n";
            }
            $text .= 'Total: GBP '.number_format($s['total_minor'] / 100, 2)."\nTest tax and delivery rates. Private sandbox copy; no email was sent externally.\n";
            // Atomic replace plus deterministic filename makes crash/retry delivery idempotent locally.
            $disk = Storage::disk('local');
            $path = 'commerce-mail/'.$this->orderId.'.eml';
            $disk->makeDirectory('commerce-mail');
            $tmp = $disk->path($path.'.'.bin2hex(random_bytes(8)).'.tmp');
            file_put_contents($tmp, $text, LOCK_EX);
            if (! rename($tmp, $disk->path($path))) {
                throw new \RuntimeException('Could not save sandbox mail.');
            }
            DB::table('commerce_mail_outbox')->where('order_id', $this->orderId)->update(['delivered_at' => now()]);
        }, 5);
    }
}
