<?php

namespace App\Http\Controllers;

use App\AfterSales\Exchanges;
use App\AfterSales\Refunds;
use App\AfterSales\Returns;
use App\Catalogue\CatalogueData as D;
use App\Fulfilment\Tracking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AfterSalesController extends Controller
{
    public function guest(Request $r)
    {
        $o = app(Tracking::class)->owned($r);

        return D::ok($r->isMethod('GET') ? app(Returns::class)->view($o) : app(Returns::class)->request($o, $r));
    }

    public function index(Request $r)
    {
        $v = $r->validate(['page' => 'nullable|integer|min:1|max:10000']);
        $page = DB::table('after_sales_returns')->orderByDesc('created_at')->orderByDesc('id')->paginate(20);

        return D::ok(['policy' => Returns::POLICY, 'items' => array_map(fn ($x) => app(Returns::class)->detail($x), $page->items()), 'page' => $page->currentPage(), 'last_page' => $page->lastPage()]);
    }

    public function inspect(Request $r, string $id)
    {
        $v = $r->validate(['received_quantity' => 'required|integer|min:1|max:1000', 'outcome' => 'required|in:accepted_resellable,accepted_damaged,rejected', 'location_id' => 'required|integer|min:1', 'reason' => 'required|string|min:3|max:1500', 'confirmed' => 'accepted']);

        return D::ok(app(Returns::class)->inspect($id, $v, auth('staff')->id()));
    }

    public function exchange(Request $r, string $id)
    {
        $v = $r->validate(['operation_id' => 'required|uuid', 'variant_id' => 'required|integer|min:1', 'expected_difference_minor' => 'required|integer|min:-99999999|max:99999999', 'confirmed' => 'accepted']);

        return D::ok(app(Exchanges::class)->issue($id, $v, auth('staff')->id()));
    }

    public function resolution(Request $r, string $id)
    {
        $v = $r->validate(['reason' => 'required|string|min:3|max:1500', 'confirmed' => 'accepted']);

        return D::ok(app(Returns::class)->resolution($id, $v, auth('staff')->id()));
    }

    public function refund(Request $r, string $id)
    {
        $v = $r->validate(['operation_id' => 'required|uuid', 'amount_minor' => 'required|integer|min:1|max:99999999', 'reason' => 'required|string|min:3|max:1500', 'scenario' => 'required|in:succeed,pending,fail,timeout_after_once', 'confirmed' => 'accepted']);

        return D::ok(app(Refunds::class)->start($id, $v, auth('staff')->id()));
    }

    public function retry(Request $r, string $id)
    {
        $r->validate(['confirmed' => 'accepted']);
        app(Refunds::class)->process($id);

        return D::ok(['attempt' => DB::table('refund_attempts')->where('id', $id)->first()]);
    }
}
