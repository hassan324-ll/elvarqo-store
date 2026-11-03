<?php

namespace App\Http\Controllers;

use App\AfterSales\Returns;
use App\Catalogue\CatalogueData as D;
use App\Commerce\Checkout;
use App\Customers\Accounts;
use App\Fulfilment\Tracking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CustomerController extends Controller
{
    public function handle(Request $r, string $action)
    {
        $a = app(Accounts::class);
        $neutral = ['message' => 'If eligible, a private verification link has been queued. Check your local test email copy.'];
        if ($action === 'register') {
            $v = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email:rfc|max:254|not_regex:/[\r\n]/', 'password' => 'required|string|min:14|max:128', 'terms' => 'accepted']);
            $a->register($v);

            return D::ok($neutral);
        }
        if ($action === 'recover') {
            $v = $r->validate(['email' => 'required|email:rfc|max:254']);
            $a->recover($v['email']);

            return D::ok($neutral);
        }
        if ($action === 'login') {
            $v = $r->validate(['email' => 'required|email:rfc|max:254', 'password' => 'required|string|max:128']);

            return D::ok($a->login($v));
        }
        if ($action === 'redeem') {
            $v = $r->validate(['token' => 'required|string|size:64|regex:/^[a-f0-9]+$/', 'purpose' => 'required|in:verify,reset,claim,email', 'password' => 'nullable|string|min:14|max:128']);
            $actor = in_array($v['purpose'], ['claim', 'email']) ? $a->owned($r) : null;
            $a->redeem($v, $actor);

            return D::ok(['message' => 'Verification completed. Sign in again if your password or email changed.']);
        }
        $c = $a->owned($r);
        if ($action === 'me') {
            return D::ok($a->public($c));
        }
        if ($action === 'logout') {
            DB::table('customer_sessions')->where('token_hash', hash('sha256', $r->bearerToken()))->delete();

            return D::ok(['signed_out' => true]);
        }
        if ($action === 'profile') {
            $v = $r->validate(['name' => 'required|string|max:100']);
            DB::table('customers')->where('id', $c->id)->update(['name' => trim($v['name']), 'updated_at' => now()]);

            return D::ok($a->public(DB::table('customers')->find($c->id)));
        }
        if ($action === 'email') {
            $v = $r->validate(['email' => 'required|email:rfc|max:254|not_regex:/[\r\n]/', 'password' => 'required|string|max:128']);
            abort_unless(Hash::check($v['password'], $c->password), 401);
            DB::transaction(fn () => $a->challenge($c, 'email', ['email' => strtolower(trim($v['email'])), 'version' => $c->auth_version], strtolower(trim($v['email']))), 5);

            return D::ok($neutral);
        }
        if ($action === 'claim') {
            $v = $r->validate(['reference' => 'required|string|max:40']);
            $a->claim($c, $v['reference']);

            return D::ok($neutral);
        }
        if ($action === 'orders') {
            $v = $r->validate(['page' => 'nullable|integer|min:1|max:10000']);
            $q = DB::table('commerce_orders as o')->join('customer_orders as c', 'c.order_id', '=', 'o.id')->where('c.customer_id', $c->id)->orderByDesc('o.created_at')->paginate(20, ['o.id', 'o.reference', 'o.order_state', 'o.payment_state', 'o.fulfilment_state', 'o.total_minor', 'o.currency', 'o.created_at']);

            return D::ok(['items' => $q->items(), 'page' => $q->currentPage(), 'last_page' => $q->lastPage()]);
        }
        if ($action === 'order') {
            $v = $r->validate(['id' => 'required|uuid']);
            $o = $a->order($c, $v['id']);

            return D::ok($this->detail($o));
        }
        if ($action === 'returns') {
            $v = $r->validate(['order_id' => 'required|uuid']);
            $o = $a->order($c, $v['order_id']);

            return D::ok(app(Returns::class)->request($o, $r));
        }
        if ($action === 'addresses') {
            if ($r->isMethod('GET')) {
                return D::ok($a->addresses($c));
            }$v = $r->validate(['id' => 'nullable|uuid', 'version' => 'required_with:id|integer|min:1', 'action' => 'required|in:save,delete', 'confirmed' => 'accepted', 'is_default' => 'nullable|boolean', 'address' => 'required_if:action,save|array:first_name,last_name,line1,line2,city,postcode,country', 'address.first_name' => 'required_if:action,save|string|max:80', 'address.last_name' => 'required_if:action,save|string|max:80', 'address.line1' => 'required_if:action,save|string|max:120', 'address.line2' => 'nullable|string|max:120', 'address.city' => 'required_if:action,save|string|max:80', 'address.postcode' => 'required_if:action,save|string|max:12|regex:/^[A-Z0-9 ]{5,12}$/i', 'address.country' => 'required_if:action,save|in:GB']);

            return D::ok($a->address($c, $v));
        }
        if ($action === 'saved') {
            if ($r->isMethod('GET')) {
                return D::ok($a->saved($c));
            }$v = $r->validate(['action' => 'required|in:merge,save,remove', 'items' => 'present|array|max:50', 'items.*' => 'required|array:product_id,variant_id', 'items.*.product_id' => 'required|integer|min:1|max:999999999999999|distinct', 'items.*.variant_id' => 'nullable|integer|min:1']);

            return D::ok($a->save($c, $v));
        }
        abort(404);
    }

    public function detail(object $o): array
    {
        $s = app(Checkout::class)->snapshot($o->id);

        return ['id' => $o->id, 'reference' => $o->reference, 'payment_state' => $o->payment_state, 'fulfilment_state' => $o->fulfilment_state, 'order_state' => $o->order_state, 'total_minor' => $o->total_minor, 'currency' => $o->currency, 'snapshot' => $s, 'tracking' => app(Tracking::class)->summary($o), 'after_sales' => app(Returns::class)->view($o)];
    }
}
