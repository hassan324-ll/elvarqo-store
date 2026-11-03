<?php

namespace App\Http\Controllers;

use App\Catalogue\CatalogueData as D;
use App\Catalogue\CatalogueWriter as W;
use App\Models\CatalogueProduct as Product;
use App\Models\CatalogueVariant as Variant;
use App\Models\StockLocation;
use App\Security\StaffAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function locations()
    {
        return D::ok(['items' => StockLocation::orderBy('id')->get()]);
    }

    public function createLocation(Request $r)
    {
        $v = $r->validate(['name' => 'required|string|max:120', 'code' => 'required|string|max:40|regex:/^[A-Z0-9_-]+$/|unique:stock_locations,code']);
        $l = StockLocation::create($v);
        StaffAudit::record('stock.location_created', auth('staff')->id(), 'location:'.$l->id);

        return D::ok(['location' => $l], 201);
    }

    public function inventory(Request $r)
    {
        $v = $r->validate(['q' => 'nullable|string|max:100', 'location_id' => 'nullable|integer|exists:stock_locations,id', 'page' => 'nullable|integer|min:1|max:10000']);
        $q = Variant::join('catalogue_products', 'product_id', '=', 'catalogue_products.id')->whereNull('catalogue_products.deleted_at')->whereNull('archived_at')->where('active', true);
        if (! empty($v['q'])) {
            $q->where(fn ($q) => $q->where('sku', 'like', '%'.$v['q'].'%')->orWhere('title', 'like', '%'.$v['q'].'%'));
        }
        $page = $q->orderBy('catalogue_variants.id')->paginate(20, ['catalogue_variants.*', 'catalogue_products.title']);
        $items = $page->getCollection()->map(function ($variant) use ($v) {
            $balances = DB::table('stock_balances')->join('stock_locations', 'location_id', '=', 'stock_locations.id')->where('variant_id', $variant->id)->when(isset($v['location_id']), fn ($q) => $q->where('location_id', $v['location_id']))->select('location_id', 'name', 'on_hand', 'reserved')->get()->map(fn ($b) => ['location_id' => $b->location_id, 'location' => $b->name, 'on_hand' => $b->on_hand, 'reserved' => $b->reserved, 'available' => $b->on_hand - $b->reserved]);

            return ['id' => $variant->id, 'product_id' => $variant->product_id, 'title' => $variant->title, 'sku' => $variant->sku, 'low_stock_threshold' => $variant->low_stock_threshold, 'balances' => $balances];
        });

        return D::ok(['items' => $items, 'page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]);
    }

    public function history(Request $r)
    {
        $v = $r->validate(['variant_id' => 'nullable|integer|exists:catalogue_variants,id', 'location_id' => 'nullable|integer|exists:stock_locations,id', 'page' => 'nullable|integer|min:1|max:10000']);
        $q = DB::table('stock_movements');
        foreach (['variant_id', 'location_id'] as $key) {
            if (isset($v[$key])) {
                $q->where($key, $v[$key]);
            }
        }$page = $q->orderByDesc('id')->paginate(30);

        return D::ok(['items' => $page->items(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]);
    }

    public function adjust(Request $r)
    {
        return $this->move($r, false);
    }

    public function transfer(Request $r)
    {
        return $this->move($r, true);
    }

    private function move(Request $r, bool $transfer)
    {
        $v = $r->validate(['reserved' => 'prohibited', 'on_hand' => 'prohibited', 'operation_id' => 'required|uuid', 'variant_id' => 'required|integer|exists:catalogue_variants,id', 'location_id' => 'required|integer|exists:stock_locations,id', 'to_location_id' => $transfer ? 'required|integer|different:location_id|exists:stock_locations,id' : 'prohibited', 'quantity' => $transfer ? 'required|integer|min:1|max:1000000' : 'prohibited', 'delta' => $transfer ? 'prohibited' : 'required|integer|not_in:0|min:-1000000|max:1000000', 'reason' => 'required|string|min:3|max:240', 'reference' => 'nullable|string|max:120', 'note' => 'nullable|string|max:2000']);
        $fingerprint = hash('sha256', json_encode([$transfer, $v['variant_id'], $v['location_id'], $v['to_location_id'] ?? null, $v['quantity'] ?? null, $v['delta'] ?? null, trim($v['reason']), $v['reference'] ?? null, $v['note'] ?? null, auth('staff')->id()]));

        return DB::transaction(function () use ($v, $fingerprint, $transfer) {
            $variant = Variant::lockForUpdate()->findOrFail($v['variant_id']);
            Product::findOrFail($variant->product_id);
            if (! $variant->active || $variant->archived_at) {
                W::fail('variant_id', 'Adjust only active variants belonging to a current product.');
            }
            $existing = DB::table('stock_operations')->where('id', $v['operation_id'])->first();
            if ($existing) {
                if ($existing->fingerprint !== $fingerprint) {
                    W::fail('operation_id', 'This retry key belongs to a different stock request.');
                }

                return D::ok(['movements' => DB::table('stock_movements')->where('operation_id', $v['operation_id'])->get(), 'replayed' => true]);
            }
            $legs = [$v['location_id'] => $transfer ? -$v['quantity'] : $v['delta']];
            if ($transfer) {
                $legs[$v['to_location_id']] = $v['quantity'];
            }ksort($legs);
            foreach ($legs as $location => $delta) {
                $l = StockLocation::lockForUpdate()->findOrFail($location);
                if (! $l->active) {
                    W::fail('location_id', 'Choose active stock locations.');
                }DB::table('stock_balances')->insertOrIgnore(['variant_id' => $variant->id, 'location_id' => $location, 'on_hand' => 0, 'reserved' => 0]);
            }
            DB::table('stock_operations')->insert(['id' => $v['operation_id'], 'fingerprint' => $fingerprint, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($legs as $location => $delta) {
                $b = DB::table('stock_balances')->where(['variant_id' => $variant->id, 'location_id' => $location])->lockForUpdate()->first();
                $after = $b->on_hand + $delta;
                if ($after < $b->reserved || $after > 1000000000) {
                    W::fail('delta', 'Resulting on-hand stock must stay at least reserved and at most 1,000,000,000.');
                }
                DB::table('stock_balances')->where('id', $b->id)->update(['on_hand' => $after]);
                DB::table('stock_movements')->insert(['operation_id' => $v['operation_id'], 'variant_id' => $variant->id, 'location_id' => $location, 'delta' => $delta, 'on_hand_after' => $after, 'reserved_after' => $b->reserved, 'staff_user_id' => auth('staff')->id(), 'reason' => trim($v['reason']), 'reference' => $v['reference'] ?? null, 'note' => $v['note'] ?? null, 'created_at' => now()]);
            }
            StaffAudit::record($transfer ? 'stock.transferred' : 'stock.adjusted', auth('staff')->id(), 'stock-operation:'.$v['operation_id']);

            return D::ok(['movements' => DB::table('stock_movements')->where('operation_id', $v['operation_id'])->get(), 'replayed' => false], 201);
        }, 3);
    }
}
