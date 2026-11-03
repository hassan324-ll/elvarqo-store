<?php

namespace App\Fulfilment;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Notifications
{
    public static function queue(string $order, string $kind, string $key, array $payload = [], ?string $fulfilment = null): void
    {
        DB::table('order_notifications')->insertOrIgnore(['id' => (string) Str::uuid(), 'order_id' => $order, 'fulfilment_id' => $fulfilment, 'kind' => $kind, 'dedupe_key' => $key, 'encrypted_payload' => Crypt::encryptString(json_encode($payload)), 'created_at' => now()]);
    }
}
