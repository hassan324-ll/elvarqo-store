<?php

namespace App\Customers;

use App\Commerce\Checkout;
use App\Commerce\Pricing;
use App\Jobs\DeliverCustomerNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Accounts
{
    public function owned(Request $r): object
    {
        $token = $r->bearerToken();
        abort_unless($token && preg_match('/^[a-f0-9]{64}$/', $token), 401);
        $s = DB::table('customer_sessions')->where('token_hash', hash('sha256', $token))->first();
        abort_unless($s && now()->lt($s->expires_at) && now()->lt(Carbon::parse($s->seen_at)->addHours(2)), 401);
        $c = DB::table('customers')->find($s->customer_id);
        abort_unless($c && $c->email_verified_at && $c->auth_version === $s->auth_version, 401);
        DB::table('customer_sessions')->where('token_hash', $s->token_hash)->update(['seen_at' => now()]);

        return $c;
    }

    public function public(object $c): array
    {
        return ['id' => $c->id, 'name' => $c->name, 'email' => $c->email, 'verified' => true];
    }

    public function challenge(object $c, string $purpose, array $payload = [], ?string $email = null): void
    {
        if (DB::table('customer_challenges')->where('customer_id', $c->id)->where('purpose', $purpose)->where('created_at', '>', now()->subMinutes(5))->exists()) {
            return;
        }
        $id = (string) Str::uuid();
        $token = bin2hex(random_bytes(32));
        DB::table('customer_challenges')->insert(['id' => $id, 'customer_id' => $c->id, 'purpose' => $purpose, 'token_hash' => hash('sha256', $token), 'payload' => json_encode($payload), 'expires_at' => now()->addMinutes(30), 'created_at' => now()]);
        DB::table('customer_notifications')->insert(['id' => $id, 'encrypted_payload' => Crypt::encryptString(json_encode(['email' => $email ?? $c->email, 'purpose' => $purpose, 'url' => rtrim(config('commerce.storefront_origin'), '/').'/account#purpose='.$purpose.'&token='.$token])), 'created_at' => now()]);
        DeliverCustomerNotification::dispatch($id)->afterCommit();
    }

    public function register(array $v): void
    {
        DB::transaction(function () use ($v) {
            $email = strtolower(trim($v['email']));
            $c = DB::table('customers')->where('email', $email)->lockForUpdate()->first();
            if (! $c) {
                $id = DB::table('customers')->insertGetId(['name' => trim($v['name']), 'email' => $email, 'password' => Hash::make($v['password']), 'created_at' => now(), 'updated_at' => now()]);
                $c = DB::table('customers')->find($id);
            }if (! $c->email_verified_at) {
                $this->challenge($c, 'verify');
            }
        }, 5);
    }

    public function login(array $v): array
    {
        $c = DB::table('customers')->where('email', strtolower(trim($v['email'])))->first();
        $ok = Hash::check($v['password'], $c?->password ?? '$2y$12$93IJXsL0slN5JOHSzLwjD.5F5AQYbIdRYZezHB1lHrDq1u5VgB2RO');
        abort_unless($c && $ok && $c->email_verified_at, 401);
        $token = bin2hex(random_bytes(32));
        DB::table('customer_sessions')->insert(['token_hash' => hash('sha256', $token), 'customer_id' => $c->id, 'auth_version' => $c->auth_version, 'seen_at' => now(), 'expires_at' => now()->addDays(14), 'created_at' => now()]);

        return ['customer' => $this->public($c), 'token' => $token];
    }

    public function recover(string $email): void
    {
        DB::transaction(function () use ($email) {
            $c = DB::table('customers')->where('email', strtolower(trim($email)))->first();
            if ($c && $c->email_verified_at) {
                $this->challenge($c, 'reset');
            }
        }, 5);
    }

    public function redeem(array $v, ?object $actor): void
    {
        DB::transaction(function () use ($v, $actor) {
            $h = DB::table('customer_challenges')->where('token_hash', hash('sha256', $v['token']))->lockForUpdate()->first();
            abort_unless($h && ! $h->used_at && now()->lt($h->expires_at) && $h->purpose === $v['purpose'], 401);
            $c = DB::table('customers')->where('id', $h->customer_id)->lockForUpdate()->first();
            $p = json_decode($h->payload, true);
            if ($h->purpose === 'verify') {
                DB::table('customers')->where('id', $c->id)->update(['email_verified_at' => now(), 'updated_at' => now()]);
            } elseif ($h->purpose === 'reset') {
                if (empty($v['password'])) {
                    Pricing::fail('password', 'Enter a new password.');
                }DB::table('customers')->where('id', $c->id)->update(['password' => Hash::make($v['password']), 'auth_version' => $c->auth_version + 1, 'updated_at' => now()]);
                DB::table('customer_sessions')->where('customer_id', $c->id)->delete();
                DB::table('customer_challenges')->where('customer_id', $c->id)->where('purpose', 'reset')->whereNull('used_at')->update(['used_at' => now()]);
            } elseif ($h->purpose === 'claim') {
                abort_unless($actor && $actor->id === $c->id, 401);
                $existing = DB::table('customer_orders')->where('order_id', $p['order_id'])->first();
                if ($existing && $existing->customer_id !== $c->id) {
                    Pricing::fail('order', 'This order is already linked to another account.');
                }if (! $existing) {
                    DB::table('customer_orders')->insert(['order_id' => $p['order_id'], 'customer_id' => $c->id, 'created_at' => now()]);
                }
            } elseif ($h->purpose === 'email') {
                abort_unless($actor && $actor->id === $c->id && $p['version'] === $c->auth_version, 401);
                if (DB::table('customers')->where('email', $p['email'])->where('id', '!=', $c->id)->exists()) {
                    Pricing::fail('email', 'This address cannot be used.');
                }DB::table('customers')->where('id', $c->id)->update(['email' => $p['email'], 'auth_version' => $c->auth_version + 1, 'updated_at' => now()]);
                DB::table('customer_sessions')->where('customer_id', $c->id)->delete();
            }
            DB::table('customer_challenges')->where('id', $h->id)->update(['used_at' => now()]);
        }, 5);
    }

    public function claim(object $c, string $reference): void
    {
        DB::transaction(function () use ($c, $reference) {
            $o = DB::table('commerce_orders')->where('reference', strtoupper(trim($reference)))->first();
            if (! $o || DB::table('customer_orders')->where('order_id', $o->id)->exists()) {
                return;
            }$s = app(Checkout::class)->snapshot($o->id);
            $this->challenge($c, 'claim', ['order_id' => $o->id], $s['contact']['email']);
        }, 5);
    }

    public function order(object $c, string $id): object
    {
        abort_unless(DB::table('customer_orders')->where('customer_id', $c->id)->where('order_id', $id)->exists(), 404);

        return DB::table('commerce_orders')->find($id);
    }

    public function addresses(object $c): array
    {
        return DB::table('customer_addresses')->where('customer_id', $c->id)->orderByDesc('is_default')->orderBy('created_at')->get()->map(fn ($a) => ['id' => $a->id, 'version' => $a->version, 'is_default' => (bool) $a->is_default, 'address' => json_decode($a->payload, true)])->all();
    }

    public function address(object $c, array $v): array
    {
        return DB::transaction(function () use ($c, $v) {
            DB::table('customers')->where('id', $c->id)->lockForUpdate()->first();
            $old = isset($v['id']) ? DB::table('customer_addresses')->where('customer_id', $c->id)->where('id', $v['id'])->first() : null;
            if (isset($v['id'])) {
                abort_unless($old, 404);
                if ($old->version !== $v['version']) {
                    Pricing::fail('version', 'This address changed. Reload before saving.');
                }
            } elseif (DB::table('customer_addresses')->where('customer_id', $c->id)->count() >= 10) {
                Pricing::fail('address', 'Address book holds up to ten addresses.');
            }
            if ($v['action'] === 'delete') {
                abort_unless($old, 404);
                DB::table('customer_addresses')->where('id', $old->id)->delete();
            } else {
                if ($v['is_default'] ?? false) {
                    DB::table('customer_addresses')->where('customer_id', $c->id)->update(['is_default' => false]);
                }$data = ['customer_id' => $c->id, 'payload' => json_encode($v['address']), 'version' => $old ? $old->version + 1 : 1, 'is_default' => $v['is_default'] ?? false, 'updated_at' => now()];
                if ($old) {
                    DB::table('customer_addresses')->where('id', $old->id)->update($data);
                } else {
                    DB::table('customer_addresses')->insert($data + ['id' => (string) Str::uuid(), 'created_at' => now()]);
                }
            }

return $this->addresses($c);
        }, 5);
    }

    public function saved(object $c): array
    {
        return DB::table('customer_saved_items')->where('customer_id', $c->id)->orderBy('created_at')->get(['product_id', 'variant_id'])->map(fn ($x) => (array) $x)->all();
    }

    public function save(object $c, array $v): array
    {
        return DB::transaction(function () use ($c, $v) {
            DB::table('customers')->where('id', $c->id)->lockForUpdate()->first();
            foreach ($v['items'] as $i) {
                $q = DB::table('customer_saved_items')->where('customer_id', $c->id)->where('product_id', $i['product_id']);
                if ($v['action'] === 'remove') {
                    $q->delete();

                    continue;
                }$variant = $i['variant_id'] ?? null;
                if ($variant && ! DB::table('catalogue_variants')->where('id', $variant)->where('product_id', $i['product_id'])->exists()) {
                    Pricing::fail('items', 'A saved variant does not belong to this product.');
                }if (! $q->exists()) {
                    $q->insert(['customer_id' => $c->id, 'product_id' => $i['product_id'], 'variant_id' => $variant, 'created_at' => now()]);
                } elseif ($v['action'] === 'save') {
                    $q->update(['variant_id' => $variant]);
                }
            }if (DB::table('customer_saved_items')->where('customer_id',$c->id)->count() > 500) {
                Pricing::fail('items','Account saved list holds 500 pieces. Guest choices have been retained.');
            }

return $this->saved($c);
        }, 5);
    }
}
