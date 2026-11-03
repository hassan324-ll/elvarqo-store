<?php

use App\Http\Controllers\CommerceController as C;
use App\Http\Middleware\CommerceOrigin;
use Illuminate\Support\Facades\Route;

Route::post('v1/payments/stripe/webhook', [C::class, 'webhook'])->middleware('throttle:stripe-webhook');
Route::prefix('v1/commerce')->middleware([CommerceOrigin::class, 'throttle:commerce-public'])->group(function () {
    Route::post('bags', [C::class, 'create'])->middleware('throttle:commerce-create');
    Route::get('bag', [C::class, 'bag']);
    Route::post('bag/items', [C::class, 'item']);
    Route::post('quote', [C::class, 'quote']);
    Route::post('checkout', [C::class, 'checkout'])->middleware('throttle:commerce-checkout');
    Route::get('orders/{id}', [C::class, 'order'])->whereUuid('id');
    Route::post('orders/{id}/resume', [C::class, 'resume'])->whereUuid('id')->middleware('throttle:commerce-resume');
});
