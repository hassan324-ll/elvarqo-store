<?php

namespace App\Fulfilment;

use App\Commerce\Bags;
use App\Commerce\Checkout;
use App\Commerce\Pricing;
use App\Commerce\WiseBankTransfer;
use App\Security\StaffAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Orders
{
    public function manualQuote(array $details, array $items): array
    {
        $map = [];
        foreach ($items as $row) {
            $id = (int) $row['variant_id'];
            if (isset($map[$id])) {
                Pricing::fail('items', 'Select each variant once.');
            }$map[$id] = (int) $row['quantity'];
        }ksort($map);

        return app(Pricing::class)->quote((object) ['items' => json_encode((object) $map), 'revision' => 1], $details);
    }

    public function manual(array $v, array $details, int $actor): array
    {
        return DB::transaction(function () use ($v, $details, $actor) {
            $fp = hash('sha256', json_encode([$actor, $v, $details]));
            $old = DB::table('manual_order_attempts')->find($v['operation_id']);
            if ($old) {
                if (! hash_equals($old->fingerprint, $fp)) {
                    Pricing::fail('operation_id', 'This retry key belongs to another manual order.');
                }

                return app(Checkout::class)->view($old->order_id);
            }
            $q = $this->manualQuote($details, $v['items']);
            if (! hash_equals($q['quote_hash'], $v['quote_hash'])) {
                Pricing::fail('quote_hash', 'Manual order prices or details changed. Review the current total.');
            }
            $bag = app(Bags::class)->create();
            $map = [];
            foreach ($v['items'] as $i) {
                $map[(int) $i['variant_id']] = (int) $i['quantity'];
            }ksort($map);
            DB::table('commerce_bags')->where('id', $bag['bag']['id'])->update(['items' => json_encode((object) $map)]);
            $r = Request::create('/', 'POST');
            $r->headers->set('Authorization', 'Bearer '.$bag['token']);
            $order = app(Checkout::class)->start($r, $details + ['attempt_id' => $v['operation_id'], 'quote_hash' => $v['quote_hash'], 'terms' => true], true);
            DB::table('manual_order_attempts')->insert(['id' => $v['operation_id'], 'order_id' => $order['id'], 'staff_user_id' => $actor, 'fingerprint' => $fp, 'created_at' => now()]);
            app(Tracking::class)->issue($order['id'], 'payment');
            StaffAudit::record('order.manual_created', $actor, 'order:'.$order['id']);

            return $order;
        }, 5);
    }

    public function detail(string $id): array
    {
        $view = app(Checkout::class)->view($id);

        return $view + ['wise_confirmation' => app(WiseBankTransfer::class)->confirmation($id), 'funded_exchange' => DB::table('exchange_links')->where('replacement_order_id', $id)->where('state', 'issued')->exists(), 'reservations' => DB::table('commerce_reservations')->where('order_id', $id)->get(), 'stock_events' => DB::table('commerce_stock_events')->where('order_id', $id)->get(), 'remaining' => app(Shipments::class)->remaining($id), 'shipments' => DB::table('order_fulfilments')->where('order_id', $id)->orderBy('created_at')->pluck('id')->map(fn ($id) => app(Shipments::class)->detail($id)), 'notes' => DB::table('order_notes as n')->join('staff_users as s', 's.id', '=', 'n.staff_user_id')->where('n.order_id', $id)->orderBy('n.created_at')->get(['n.id', 'n.body', 'n.created_at', 's.name as staff_name']), 'support_requests' => DB::table('order_support_requests')->where('order_id', $id)->orderByDesc('created_at')->get(), 'notifications' => DB::table('order_notifications')->where('order_id', $id)->orderBy('created_at')->get(['id', 'kind', 'fulfilment_id', 'created_at', 'delivered_at']), 'manual' => DB::table('manual_order_attempts')->where('order_id', $id)->exists(), 'locations' => DB::table('stock_locations')->where('active', true)->get(['id', 'name', 'code']), 'courier' => ['provider' => 'Local sandbox only', 'services' => config('fulfilment.services'), 'origin_note' => config('fulfilment.origin_note')]];
    }
}
