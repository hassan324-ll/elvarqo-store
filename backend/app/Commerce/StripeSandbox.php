<?php

namespace App\Commerce;

use Illuminate\Support\Facades\Http;

class StripeSandbox
{
    public function configured(): bool
    {
        return config('commerce.enabled') && str_starts_with(config('commerce.secret'), 'sk_test_') && str_starts_with(config('commerce.webhook_secret'), 'whsec_');
    }

    public function ready(): void
    {
        abort_unless($this->configured(), 503, 'Stripe sandbox is not configured.');
    }

    private function request(string $method, string $path, array $data = [], ?string $key = null): array
    {
        $this->ready();
        $http = Http::withToken(config('commerce.secret'))->acceptJson()->asForm()->connectTimeout(5)->timeout(20)->withHeaders(['Stripe-Version' => '2025-06-30.basil'] + ($key ? ['Idempotency-Key' => $key] : []));
        $r = $method === 'GET' ? $http->get('https://api.stripe.com/v1/'.$path) : $http->post('https://api.stripe.com/v1/'.$path, $data);
        if (! $r->successful()) {
            throw new \RuntimeException('Stripe request was not successful.');
        }
        $j = $r->json();
        if (! is_array($j)) {
            throw new \RuntimeException('Invalid Stripe response.');
        }

        return $j;
    }

    public function parameters(object $order, array $s, string $attempt, int $expires): array
    {
        $origin = rtrim(config('commerce.storefront_origin'), '/');

        return ['mode' => 'payment', 'payment_method_types' => ['card'], 'client_reference_id' => $order->id, 'customer_email' => $s['contact']['email'], 'metadata' => ['order_id' => $order->id, 'checkout_attempt' => $attempt], 'line_items' => [['quantity' => 1, 'price_data' => ['currency' => 'gbp', 'unit_amount' => $s['total_minor'], 'product_data' => ['name' => 'ELVARQO '.$order->reference.' — sandbox order (test rates)', 'description' => 'Order total includes merchandise, test discount, test delivery and test tax shown in your review.']]]], 'success_url' => $origin.'/checkout/confirmation?order='.$order->id, 'cancel_url' => $origin.'/checkout/confirmation?order='.$order->id.'&returned=1', 'expires_at' => $expires];
    }

    public function create(array $parameters, string $attempt): array
    {
        return $this->request('POST', 'checkout/sessions', $parameters, 'elvarqo-checkout-'.$attempt);
    }

    public function get(string $id): array
    {
        if (! preg_match('/^cs_test_[A-Za-z0-9_]+$/', $id)) {
            throw new \RuntimeException('Unexpected Stripe session.');
        }

        return $this->request('GET', 'checkout/sessions/'.$id);
    }

    public function expire(string $id): array
    {
        return $this->request('POST', 'checkout/sessions/'.$id.'/expire', [], 'elvarqo-expire-'.$id);
    }

    public function signature(string $body, string $header): array
    {
        $secret = config('commerce.webhook_secret');
        abort_unless(str_starts_with($secret, 'whsec_'), 503);
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }if ($pair[0] === 't' && ctype_digit($pair[1])) {
                $timestamp = (int) $pair[1];
            }if ($pair[0] === 'v1') {
                $signatures[] = $pair[1];
            }
        }
        abort_unless($timestamp && abs(time() - $timestamp) <= 300, 400);
        $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
        abort_unless(count(array_filter($signatures, fn ($s) => hash_equals($expected, $s))) > 0, 400);
        $e = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        abort_unless(is_array($e) && is_string($e['id'] ?? null) && preg_match('/^evt_[A-Za-z0-9_]{1,200}$/', $e['id']) && ($e['livemode'] ?? true) === false && is_string($e['type'] ?? null), 400);

        return $e;
    }
}
