<?php

namespace App\Http\Controllers;

use App\Catalogue\CatalogueData as D;
use App\Commerce\Bags;
use App\Commerce\Checkout;
use App\Commerce\Pricing;
use App\Commerce\StripeSandbox;
use App\Commerce\WiseBankTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommerceController extends Controller
{
    public function create()
    {
        return D::ok(app(Bags::class)->create(), 201);
    }

    public function bag(Request $r)
    {
        return D::ok(app(Bags::class)->view(app(Bags::class)->owned($r)));
    }

    public function item(Request $r)
    {
        $v = $r->validate(['variant_id' => 'required|integer|min:1', 'quantity' => 'required|integer|min:0|max:10', 'revision' => 'required|integer|min:1']);

        return D::ok(app(Bags::class)->change($r, $v));
    }

    public function details(Request $r, bool $start = false): array
    {
        $rules = ['revision' => 'required|integer|min:1', 'email' => 'required|email:rfc|max:254', 'address' => 'required|array:first_name,last_name,line1,line2,city,postcode,country', 'billing_address' => 'nullable|array:first_name,last_name,line1,line2,city,postcode,country', 'delivery_method' => 'required|string|in:standard,express', 'discount_code' => 'nullable|string|max:40'];
        foreach (['address', 'billing_address'] as $prefix) {
            $optional = $prefix === 'billing_address' ? 'required_with:billing_address' : 'required';
            foreach (['first_name' => 80, 'last_name' => 80, 'line1' => 160, 'city' => 100] as $name => $len) {
                $rules[$prefix.'.'.$name] = $optional.'|string|max:'.$len.'|not_regex:/[\r\n\x00]/';
            }$rules[$prefix.'.line2'] = 'nullable|string|max:160|not_regex:/[\r\n\x00]/';
            $rules[$prefix.'.country'] = $optional.'|in:GB';
            $rules[$prefix.'.postcode'] = [$optional, 'string', 'max:10', 'regex:/^(GIR ?0AA|[A-Z]{1,2}[0-9][A-Z0-9]? ?[0-9][A-Z]{2})$/i'];
        }
        if ($start) {
            $rules += ['attempt_id' => 'required|uuid', 'quote_hash' => 'required|string|size:64', 'terms' => 'accepted'];
        }
        $v = $r->validate($rules);
        $v['revision'] = (int) $v['revision'];
        foreach (['address', 'billing_address'] as $prefix) {
            if (! empty($v[$prefix])) {
                $v[$prefix] = array_map(fn ($s) => is_string($s) ? trim($s) : $s, $v[$prefix]);
                $v[$prefix]['postcode'] = strtoupper(preg_replace('/\s+/', '', $v[$prefix]['postcode']));
                $v[$prefix]['line2'] = $v[$prefix]['line2'] ?? '';
            }
        }
        $v['discount_code'] = strtoupper(trim($v['discount_code'] ?? ''));

        return $v;
    }

    public function quote(Request $r)
    {
        $v = $this->details($r);
        $b = app(Bags::class)->owned($r);
        if ($b->revision !== $v['revision']) {
            Pricing::fail('revision', 'Refresh your bag before reviewing.');
        }

        return D::ok(app(Pricing::class)->quote($b, $v));
    }

    public function checkout(Request $r)
    {
        return D::ok(app(Checkout::class)->start($r, $this->details($r, true)));
    }

    private function ownedOrder(Request $r, string $id): object
    {
        $bag = app(Bags::class)->owned($r);
        $o = DB::table('commerce_orders')->where('id', $id)->where('bag_id', $bag->id)->first();
        abort_unless($o, 404);

        return $o;
    }

    public function order(Request $r, string $id)
    {
        $this->ownedOrder($r, $id);

        return D::ok(app(Checkout::class)->view($id));
    }

    public function resume(Request $r, string $id)
    {
        $o = $this->ownedOrder($r, $id);
        try {
            if ($o->payment_state === 'creating') {
                app(Checkout::class)->ensureSession($id);
            } else {
                app(Checkout::class)->reconcile($id, true);
            }
        } catch (\Throwable $e) {
            return D::ok(app(Checkout::class)->view($id) + ['refresh_unavailable' => true]);
        }

        return D::ok(app(Checkout::class)->view($id));
    }

    public function webhook(Request $r)
    {
        abort_if(strlen($r->getContent()) > 262144, 413);
        try {
            $e = app(StripeSandbox::class)->signature($r->getContent(), $r->header('Stripe-Signature', ''));
        } catch (\JsonException $e) {
            abort(400);
        }
        if (DB::table('commerce_webhook_events')->where('id', $e['id'])->exists()) {
            return D::ok(['received' => true]);
        }
        if (! in_array($e['type'], ['checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.async_payment_failed', 'checkout.session.expired'])) {
            return D::ok(['received' => true, 'ignored' => true]);
        }
        $session = $e['data']['object'] ?? [];
        $id = $session['metadata']['order_id'] ?? null;
        $o = is_string($id) ? DB::table('commerce_orders')->find($id) : null;
        abort_unless($o && $o->payment_method === 'stripe_sandbox', 400);
        $p = DB::table('commerce_payments')->where('order_id', $id)->first();
        if (! $p->session_id) {
            app(Checkout::class)->ensureSession($id);
            $p = DB::table('commerce_payments')->where('order_id', $id)->first();
        }
        abort_unless($p->session_id && $p->session_id === ($session['id'] ?? null), 503);
        app(Checkout::class)->reconcile($id, false, $e, hash('sha256', $r->getContent()));

        return D::ok(['received' => true]);
    }

    public function staffIndex(Request $r)
    {
        $v = $r->validate(['page' => 'nullable|integer|min:1|max:10000']);
        $page = DB::table('commerce_orders')->orderByDesc('created_at')->orderByDesc('id')->paginate(25, ['id', 'reference', 'order_state', 'payment_state', 'fulfilment_state', 'total_minor', 'currency', 'created_at']);

        return D::ok(['items' => $page->items(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'configuration' => ['provider' => config('commerce.provider') === 'wise_bank_transfer' ? 'Wise bank transfer — manual receipt confirmation' : 'Stripe sandbox', 'enabled' => config('commerce.provider') === 'wise_bank_transfer' ? app(WiseBankTransfer::class)->configured() : app(StripeSandbox::class)->configured(), 'market' => 'GB', 'currency' => 'GBP', 'test_rates' => true, 'delivery' => config('commerce.delivery'), 'discount' => config('commerce.discount'), 'tax_basis_points' => config('commerce.tax_basis_points'), 'mail' => 'Private sandbox .eml only; no external delivery']]);
    }

    public function staffShow(string $id)
    {
        return D::ok(app(Checkout::class)->view($id) + ['reservations' => DB::table('commerce_reservations')->where('order_id', $id)->get(), 'stock_events' => DB::table('commerce_stock_events')->where('order_id', $id)->get()]);
    }
}
