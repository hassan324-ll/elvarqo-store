<?php

namespace App\Http\Controllers;

use App\Catalogue\Availability;
use App\Catalogue\CatalogueData;
use App\Models\CatalogueProduct;
use Illuminate\Support\Facades\DB;

class OverviewController extends Controller
{
    public function __invoke()
    {
        $staff = auth('staff')->user();
        $data = ['catalogue' => null, 'orders' => null, 'paid_totals' => null];
        if ($staff->canAccess('catalogue.view')) {
            $products = CatalogueProduct::query();
            $variants = DB::table('catalogue_variants as v')->join('catalogue_products as p', 'p.id', '=', 'v.product_id')
                ->whereNull('p.deleted_at')->whereNull('v.archived_at')->where('v.active', true);
            $balances = (clone $variants)->join('stock_balances as b', 'b.variant_id', '=', 'v.id')
                ->join('stock_locations as l', 'l.id', '=', 'b.location_id')->where('l.active', true);
            $data['catalogue'] = [
                'stock_tracked' => Availability::tracked(),
                'products' => (clone $products)->count(),
                'published' => (clone $products)->where('status', 'published')->count(),
                'drafts' => (clone $products)->where('status', 'draft')->count(),
                'variants' => (clone $variants)->count(),
                'available_units' => (int) (clone $balances)->sum(DB::raw('b.on_hand - b.reserved')),
                'recent' => (clone $products)->orderByDesc('created_at')->orderByDesc('id')->limit(10)->get(['id', 'title', 'status']),
            ];
        }
        if ($staff->canAccess('fulfilment.view')) {
            $data['orders'] = [
                'total' => DB::table('commerce_orders')->count(),
                'recent' => DB::table('commerce_orders')->orderByDesc('created_at')->orderByDesc('id')->limit(10)
                    ->get(['id', 'reference', 'payment_state', 'fulfilment_state', 'total_minor', 'currency']),
            ];
        }
        if ($staff->canAccess('finance.view')) {
            // Gross recorded paid order totals, grouped by currency. Not net sales:
            // no subtraction of sandbox refunds, no unpaid or exchange-credit orders.
            $data['paid_totals'] = DB::table('commerce_orders')->where('payment_state', 'paid')->whereNotNull('paid_at')
                ->groupBy('currency')->selectRaw('currency, SUM(total_minor) as amount_minor, COUNT(*) as orders')->get();
        }

        return CatalogueData::ok($data)->header('Cache-Control', 'no-store');
    }
}
