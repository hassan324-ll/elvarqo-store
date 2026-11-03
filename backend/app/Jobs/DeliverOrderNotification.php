<?php

namespace App\Jobs;

use App\Commerce\Checkout;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeliverOrderNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $notificationId) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $n = DB::table('order_notifications')->where('id', $this->notificationId)->lockForUpdate()->first();
            if (! $n || $n->delivered_at) {
                return;
            }$s = app(Checkout::class)->snapshot($n->order_id);
            $o = DB::table('commerce_orders')->find($n->order_id);
            $payload = json_decode(Crypt::decryptString($n->encrypted_payload), true);
            $text = 'To: '.$s['contact']['email']."\r\nSubject: ELVARQO sandbox ".$n->kind.' / '.$o->reference."\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nPrivate local sandbox notification. No external email sent.\nOrder: ".$o->reference."\n";
            if (isset($payload['url'])) {
                $text .= 'One-use link (30 minutes): '.$payload['url']."\n";
            }
            if ($n->fulfilment_id) {
                $f = DB::table('order_fulfilments')->find($n->fulfilment_id);
                $text .= 'Local sandbox tracking: '.$f->tracking_number."\nStatus: ".($payload['state'] ?? $f->state)."\nNo real carrier shipment exists.\n";
            }
            if ($n->kind === 'support_received') {
                $text .= "Your support request is saved for staff review.\n";
            }
            $text .= 'Request tracking access: '.rtrim(config('commerce.storefront_origin'), '/')."/track\n";
            $disk = Storage::disk('local');
            $disk->makeDirectory('order-notifications');
            $path = $disk->path('order-notifications/'.$n->id.'.eml');
            $tmp = $path.'.'.bin2hex(random_bytes(8)).'.tmp';
            if (file_put_contents($tmp, $text, LOCK_EX) === false || ! rename($tmp, $path)) {
                throw new \RuntimeException('Notification copy could not be saved.');
            }DB::table('order_notifications')->where('id', $n->id)->update(['delivered_at' => now()]);
        }, 5);
    }
}
