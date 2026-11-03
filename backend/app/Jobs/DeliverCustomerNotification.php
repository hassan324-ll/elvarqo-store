<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeliverCustomerNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $notificationId) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $n = DB::table('customer_notifications')->where('id', $this->notificationId)->lockForUpdate()->first();
            if (! $n || $n->delivered_at) {
                return;
            }$p = json_decode(Crypt::decryptString($n->encrypted_payload), true);
            $text = 'To: '.$p['email']."\r\nSubject: ELVARQO ".$p['purpose']."\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nPrivate local test copy. No external email sent.\nSingle-use link expires in 30 minutes:\n".$p['url']."\n";
            $disk = Storage::disk('local');
            $disk->makeDirectory('customer-notifications');
            $path = $disk->path('customer-notifications/'.$n->id.'.eml');
            $tmp = $path.'.'.bin2hex(random_bytes(8)).'.tmp';
            if (file_put_contents($tmp, $text, LOCK_EX) === false || ! rename($tmp, $path)) {
                throw new \RuntimeException('Could not write private notification.');
            }DB::table('customer_notifications')->where('id', $n->id)->update(['delivered_at' => now()]);
        }, 5);
    }
}
