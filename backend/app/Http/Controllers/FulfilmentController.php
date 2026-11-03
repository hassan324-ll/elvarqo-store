<?php

namespace App\Http\Controllers;

use App\Catalogue\CatalogueData as D;
use App\Commerce\Pricing;
use App\Commerce\StripeSandbox;
use App\Commerce\WiseBankTransfer;
use App\Fulfilment\Notifications;
use App\Fulfilment\Orders;
use App\Fulfilment\Shipments;
use App\Fulfilment\Tracking;
use App\Security\StaffAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FulfilmentController extends Controller
{
    public function index(Request $r)
    {
        $v = $r->validate(['page' => 'nullable|integer|min:1|max:10000', 'q' => 'nullable|string|max:100', 'payment' => 'nullable|in:paid,open,creating,pending,failed,expired,review,awaiting_payment_link,awaiting_bank_transfer,exchange_credit,awaiting_exchange_payment', 'fulfilment' => 'nullable|in:unfulfilled,partially_fulfilled,fulfilled,delivered,on_hold']);
        $q = DB::table('commerce_orders as o');
        if (! empty($v['q'])) {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($v['q'])).'%';
            $q->where(function ($q) use ($like) {
                $q->whereRaw("o.reference LIKE ? ESCAPE '!'", [$like])->orWhereExists(function ($sub) use ($like) {
                    $sub->selectRaw('1')->from('commerce_order_addresses as a')->whereColumn('a.order_id', 'o.id')->where('a.kind', 'shipping')->where(fn ($a) => $a->whereRaw("a.first_name LIKE ? ESCAPE '!'", [$like])->orWhereRaw("a.last_name LIKE ? ESCAPE '!'", [$like]));
                });
            });
        }foreach (['payment' => 'payment_state', 'fulfilment' => 'fulfilment_state'] as $key => $column) {
            if (! empty($v[$key])) {
                $q->where('o.'.$column, $v[$key]);
            }
        }$page = $q->orderByDesc('o.created_at')->orderByDesc('o.id')->paginate(25, ['o.*']);

        return D::ok(['items' => $page->items(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'configuration' => ['provider' => config('commerce.provider') === 'wise_bank_transfer' ? 'Wise bank transfer — manual receipt confirmation' : 'Stripe sandbox', 'enabled' => config('commerce.provider') === 'wise_bank_transfer' ? app(WiseBankTransfer::class)->configured() : app(StripeSandbox::class)->configured(), 'market' => 'GB', 'currency' => 'GBP', 'test_rates' => true, 'delivery' => config('commerce.delivery'), 'discount' => config('commerce.discount'), 'tax_basis_points' => config('commerce.tax_basis_points'), 'mail' => 'Private local notification copies only; no external delivery', 'courier' => 'Local sandbox only; no real postage']]);
    }

    public function show(string $id)
    {
        return D::ok(app(Orders::class)->detail($id));
    }

    public function confirmWise(Request $r, string $id)
    {
        $v = $r->validate(['operation_id' => 'required|uuid', 'confirmed' => 'accepted', 'amount_minor' => 'required|integer|min:1|max:100000000', 'currency' => 'required|in:GBP', 'payment_reference' => 'required|string|max:40|not_regex:/[\r\n\x00]/', 'receipt_reference' => ['required', 'string', 'regex:/^[A-Za-z0-9._-]{3,120}$/']]);
        app(WiseBankTransfer::class)->confirm($id, $v, auth('staff')->id());

        return D::ok(app(Orders::class)->detail($id));
    }

    public function note(Request $r, string $id)
    {
        $v = $r->validate(['operation_id' => 'required|uuid', 'body' => 'required|string|min:1|max:2000']);

        return DB::transaction(function () use ($v, $id) {
            abort_unless(DB::table('commerce_orders')->where('id', $id)->exists(), 404);
            $old = DB::table('order_notes')->find($v['operation_id']);
            if ($old) {
                if ($old->order_id !== $id || $old->body !== trim($v['body']) || $old->staff_user_id !== auth('staff')->id()) {
                    Pricing::fail('operation_id', 'This note key belongs to another request.');
                }

                return D::ok(['saved' => true]);
            }DB::table('order_notes')->insert(['id' => $v['operation_id'], 'order_id' => $id, 'staff_user_id' => auth('staff')->id(), 'body' => trim($v['body']), 'created_at' => now()]);
            StaffAudit::record('order.note_added', auth('staff')->id(), 'order:'.$id);

            return D::ok(['saved' => true]);
        }, 5);
    }

    private function manualInput(Request $r, bool $create = false): array
    {
        $v = $r->validate(['items' => 'required|array|min:1|max:30', 'items.*' => 'required|array:variant_id,quantity', 'items.*.variant_id' => 'required|integer|min:1|distinct', 'items.*.quantity' => 'required|integer|min:1|max:10'] + ($create ? ['operation_id' => 'required|uuid', 'quote_hash' => 'required|string|size:64', 'confirmed' => 'accepted'] : []));
        $copy = Request::create('/', 'POST', array_replace($r->all(), ['revision' => 1]));
        $details = app(CommerceController::class)->details($copy);

        return [$v, $details];
    }

    public function manualQuote(Request $r)
    {
        [$v,$details] = $this->manualInput($r);

        return D::ok(app(Orders::class)->manualQuote($details, $v['items']));
    }

    public function manualCreate(Request $r)
    {
        [$v,$details] = $this->manualInput($r, true);

        return D::ok(app(Orders::class)->manual($v, $details, auth('staff')->id()));
    }

    public function invitation(Request $r, string $id)
    {
        $r->validate(['confirmed' => 'accepted']);

        return DB::transaction(function () use ($id) {
            $o = DB::table('commerce_orders')->where('id', $id)->lockForUpdate()->first();
            abort_unless($o, 404);
            if (! in_array($o->payment_state, ['awaiting_payment_link', 'awaiting_bank_transfer']) || ! DB::table('manual_order_attempts')->where('order_id', $id)->exists() || now()->gte($o->reservation_expires_at)) {
                Pricing::fail('order', 'Only an unexpired unpaid manual invitation can be reissued.');
            }if (DB::table('order_access_links')->where('order_id', $id)->where('purpose', 'payment')->where('created_at', '>', now()->subMinutes(5))->exists()) {
                Pricing::fail('order', 'Wait five minutes before requesting another invitation.');
            }app(Tracking::class)->issue($id, 'payment');
            StaffAudit::record('order.payment_invitation_reissued', auth('staff')->id(), 'order:'.$id);

            return D::ok(['queued' => true]);
        }, 5);
    }

    public function quote(Request $r, string $id)
    {
        $v = $r->validate(['items' => 'required|array|min:1|max:30', 'items.*' => 'required|array:order_item_id,quantity', 'items.*.order_item_id' => 'required|integer|min:1|distinct', 'items.*.quantity' => 'required|integer|min:1|max:1000', 'location_id' => 'required|integer|min:1', 'service' => 'required|string|in:sandbox_standard,sandbox_express', 'parcel' => 'required|array:weight_grams,length_cm,width_cm,height_cm', 'parcel.weight_grams' => 'required|integer|min:1|max:30000', 'parcel.length_cm' => 'required|integer|min:1|max:120', 'parcel.width_cm' => 'required|integer|min:1|max:120', 'parcel.height_cm' => 'required|integer|min:1|max:120', 'instructions' => 'nullable|string|max:240|not_regex:/[\r\n\x00]/']);

        return D::ok(app(Shipments::class)->quote($id, $v, auth('staff')->id()));
    }

    public function bookQuote(Request $r, string $id)
    {
        $v = $r->validate(['quote_id' => 'required|uuid', 'confirmed' => 'accepted']);
        $f = app(Shipments::class)->prepare($id, $v['quote_id'], auth('staff')->id());

        return D::ok(app(Shipments::class)->book($f->id, auth('staff')->id()));
    }

    public function action(Request $r, string $id, string $action)
    {
        $r->validate(['confirmed' => 'accepted']);
        $actor = auth('staff')->id();

        return D::ok(match ($action) {
            'retry' => app(Shipments::class)->book($id, $actor),'cancel' => app(Shipments::class)->cancel($id, $actor),'dispatch' => app(Shipments::class)->dispatch($id, $actor)
        });
    }

    public function label(string $id)
    {
        $f = DB::table('order_fulfilments')->find($id);
        abort_unless($f && $f->state !== 'cancelled', 404);
        $label = DB::table('fulfilment_labels')->where('fulfilment_id', $id)->first();
        abort_unless($label && Storage::disk('local')->exists($label->path), 404);
        StaffAudit::record('shipment.label_downloaded', auth('staff')->id(), 'shipment:'.$id);

        return response(Storage::disk('local')->get($label->path))->header('Content-Type', 'image/svg+xml')->header('Content-Disposition', 'attachment; filename="ELVARQO-SANDBOX-'.$id.'.svg"')->header('X-Content-Type-Options', 'nosniff')->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
    }

    public function requestTracking(Request $r)
    {
        $v = $r->validate(['reference' => 'required|string|max:60', 'email' => 'required|email:rfc|max:254']);
        app(Tracking::class)->request($v);

        return D::ok(['message' => 'If the details match, a secure tracking link will be prepared. Local sandbox delivery uses private email copies.']);
    }

    public function redeemTracking(Request $r)
    {
        $v = $r->validate(['token' => 'required|string|regex:/^[a-f0-9]{64}$/']);

        return D::ok(app(Tracking::class)->redeem($v['token'], 'tracking'));
    }

    public function tracking(Request $r)
    {
        return D::ok(app(Tracking::class)->view($r));
    }

    public function logoutTracking(Request $r)
    {
        $o = app(Tracking::class)->owned($r);
        DB::table('order_tracking_sessions')->where('token_hash', hash('sha256', $r->bearerToken()))->delete();

        return D::ok(['signed_out' => true]);
    }

    public function redeemPayment(Request $r)
    {
        $v = $r->validate(['token' => 'required|string|regex:/^[a-f0-9]{64}$/']);

        return D::ok(app(Tracking::class)->redeem($v['token'], 'payment'));
    }

    public function support(Request $r)
    {
        $o = app(Tracking::class)->owned($r);
        $v = $r->validate(['operation_id' => 'required|uuid', 'subject' => 'required|string|min:3|max:120', 'message' => 'required|string|min:5|max:2000']);

        return DB::transaction(function () use ($o, $v) {
            DB::table('commerce_orders')->where('id', $o->id)->lockForUpdate()->first();
            $old = DB::table('order_support_requests')->find($v['operation_id']);
            if ($old) {
                if ($old->order_id !== $o->id || $old->subject !== trim($v['subject']) || $old->message !== trim($v['message'])) {
                    Pricing::fail('operation_id', 'This retry key belongs to another support request.');
                }

                return D::ok(['id' => $old->id, 'saved' => true]);
            }if (DB::table('order_support_requests')->where('order_id', $o->id)->where('created_at', '>', now()->subDay())->count() >= 5) {
                Pricing::fail('message', 'The daily support request limit has been reached.');
            }DB::table('order_support_requests')->insert(['id' => $v['operation_id'], 'order_id' => $o->id, 'subject' => trim($v['subject']), 'message' => trim($v['message']), 'state' => 'open', 'created_at' => now()]);
            Notifications::queue($o->id, 'support_received', 'support:'.$v['operation_id']);

            return D::ok(['id' => $v['operation_id'], 'saved' => true]);
        }, 5);
    }

    public function webhook(Request $r)
    {
        app(Tracking::class)->event($r->getContent(), $r->header('X-Sandbox-Signature', ''));

        return D::ok(['received' => true, 'sandbox' => true]);
    }
}
