<?php

use App\Http\Controllers\FulfilmentController as F;
use App\Http\Middleware\CommerceOrigin;
use App\Http\Middleware\StaffOrigin;
use App\Http\Middleware\StaffPermission;
use App\Http\Middleware\StaffSession;
use Illuminate\Support\Facades\Route;

Route::post('v1/courier/sandbox/webhook', [F::class, 'webhook'])->middleware('throttle:courier-webhook');
Route::prefix('v1/tracking')->middleware([CommerceOrigin::class, 'throttle:tracking-public'])->group(function () {
    Route::post('request', [F::class, 'requestTracking'])->middleware('throttle:tracking-link');
    Route::post('redeem', [F::class, 'redeemTracking'])->middleware('throttle:tracking-redeem');
    Route::get('order', [F::class, 'tracking']);
    Route::post('logout', [F::class, 'logoutTracking']);
    Route::post('support', [F::class, 'support'])->middleware('throttle:tracking-support');
    Route::post('payment-redeem', [F::class, 'redeemPayment'])->middleware('throttle:tracking-redeem');
});
Route::prefix('v1/staff/orders')->middleware(['web', StaffOrigin::class, StaffSession::class, StaffPermission::class.':fulfilment.view', 'throttle:order-staff'])->group(function () {
    Route::get('/', [F::class, 'index']);
    Route::get('{id}', [F::class, 'show'])->whereUuid('id');
    Route::post('{id}/confirm-wise-payment', [F::class, 'confirmWise'])->whereUuid('id')->middleware(StaffPermission::class.':finance.confirm');
    Route::middleware(StaffPermission::class.':fulfilment.manage')->group(function () {
        Route::post('manual/quote', [F::class, 'manualQuote']);
        Route::post('manual/create', [F::class, 'manualCreate']);
        Route::post('{id}/notes', [F::class, 'note'])->whereUuid('id');
        Route::post('{id}/payment-invitation', [F::class, 'invitation'])->whereUuid('id');
        Route::post('{id}/shipment-quotes', [F::class, 'quote'])->whereUuid('id');
        Route::post('{id}/shipments', [F::class, 'bookQuote'])->whereUuid('id');
    });
});
Route::prefix('v1/staff/shipments')->middleware(['web', StaffOrigin::class, StaffSession::class, StaffPermission::class.':fulfilment.view', 'throttle:shipment-staff'])->group(function () {
    Route::get('{id}/label', [F::class, 'label'])->whereUuid('id');
    Route::post('{id}/{action}', [F::class, 'action'])->whereUuid('id')->whereIn('action', ['retry', 'cancel', 'dispatch'])->middleware(StaffPermission::class.':fulfilment.manage');
});
