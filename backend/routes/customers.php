<?php

use App\Http\Controllers\AfterSalesController as A;
use App\Http\Controllers\CustomerController as C;
use App\Http\Middleware\CommerceOrigin;
use App\Http\Middleware\StaffOrigin;
use App\Http\Middleware\StaffPermission;
use App\Http\Middleware\StaffSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/customer')->middleware([CommerceOrigin::class, 'throttle:customer-public'])->group(function () {
    Route::get('{action}', [C::class, 'handle'])->whereIn('action', ['me', 'orders', 'order', 'addresses', 'saved']);
    foreach (['register', 'recover', 'login', 'redeem', 'email', 'claim', 'logout', 'profile', 'addresses', 'saved', 'returns'] as $action) {
        $route = Route::post($action, fn (Request $r) => app(C::class)->handle($r, $action));
        if (in_array($action, ['register', 'recover', 'login', 'redeem', 'email', 'claim'])) {
            $route->middleware('throttle:customer-auth');
        }
    }
});
Route::prefix('v1/tracking')->middleware([CommerceOrigin::class, 'throttle:tracking-public'])->group(function () {
    Route::get('returns', [A::class, 'guest']);
    Route::post('returns', [A::class, 'guest']);
});
Route::prefix('v1/staff/after-sales')->middleware(['web', StaffOrigin::class, StaffSession::class, 'throttle:after-sales-staff'])->group(function () {
    Route::get('returns', [A::class, 'index'])->middleware(StaffPermission::class.':fulfilment.view');
    Route::get('refunds', [A::class, 'index'])->middleware(StaffPermission::class.':finance.view');
    Route::post('{id}/inspect', [A::class, 'inspect'])->whereUuid('id')->middleware(StaffPermission::class.':fulfilment.manage');
    Route::post('{id}/exchange', [A::class, 'exchange'])->whereUuid('id')->middleware(StaffPermission::class.':fulfilment.manage');
    Route::post('{id}/resolution', [A::class, 'resolution'])->whereUuid('id')->middleware(StaffPermission::class.':fulfilment.manage');
    Route::post('{id}/refund', [A::class, 'refund'])->whereUuid('id')->middleware(StaffPermission::class.':finance.refund');
    Route::post('{id}/retry', [A::class, 'retry'])->whereUuid('id')->middleware(StaffPermission::class.':finance.refund');
});
