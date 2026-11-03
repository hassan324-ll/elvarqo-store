<?php

namespace App\Commerce;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Bags
{
    public function create(): array
    {
        $token = bin2hex(random_bytes(32));
        $id = (string) Str::uuid();
        DB::table('commerce_bags')->insert(['id' => $id, 'token_hash' => hash('sha256', $token), 'items' => '{}', 'revision' => 1, 'expires_at' => now()->addDays(30), 'created_at' => now(), 'updated_at' => now()]);

        return ['token' => $token, 'bag' => $this->view(DB::table('commerce_bags')->find($id))];
    }

    public function owned(Request $r, bool $lock = false): object
    {
        $token = $r->bearerToken();
        abort_unless($token && preg_match('/^[a-f0-9]{64}$/', $token), 401);
        $q = DB::table('commerce_bags')->where('token_hash', hash('sha256', $token))->where('expires_at', '>', now());
        $bag = ($lock ? $q->lockForUpdate() : $q)->first();
        abort_unless($bag, 401);

        return $bag;
    }

    public function view(object $bag): array
    {
        return ['id' => $bag->id, 'revision' => $bag->revision, 'items' => app(Pricing::class)->lines(json_decode($bag->items, true)), 'active_order_id' => $bag->active_order_id, 'rates' => config('commerce.delivery'), 'test_rates' => true, 'sandbox_enabled' => app(StripeSandbox::class)->configured(), 'payment_provider' => config('commerce.provider'), 'checkout_enabled' => config('commerce.provider') === 'wise_bank_transfer' ? app(WiseBankTransfer::class)->configured() : app(StripeSandbox::class)->configured()];
    }

    public function change(Request $r, array $v): array
    {
        foreach (['variant_id', 'quantity', 'revision'] as $key) {
            $v[$key] = (int) $v[$key];
        }

        return DB::transaction(function () use ($r, $v) {
            $b = $this->owned($r, true);
            if ($b->active_order_id) {
                Pricing::fail('bag', 'An order is already awaiting payment. Resume it or wait for verified expiry.');
            }
            if ($b->revision !== $v['revision']) {
                Pricing::fail('revision', 'Your bag changed in another tab. Refresh and try again.');
            }
            $items = json_decode($b->items, true);
            $id = $v['variant_id'];
            if ($v['quantity'] === 0) {
                unset($items[$id]);
            } else {
                $items[$id] = $v['quantity'];
            }
            if (count($items) > 30) {
                Pricing::fail('items', 'A bag can contain at most 30 variants.');
            }
            if ($v['quantity'] > 0) {
                $lines = app(Pricing::class)->lines([$id => $v['quantity']]);
                if ($lines[0]['unavailable']) {
                    Pricing::fail('quantity', 'This quantity is unavailable.');
                }
            }
            ksort($items);
            DB::table('commerce_bags')->where('id', $b->id)->update(['items' => json_encode((object) $items), 'revision' => $b->revision + 1, 'updated_at' => now()]);

            return $this->view(DB::table('commerce_bags')->find($b->id));
        }, 5);
    }
}
