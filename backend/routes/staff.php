<?php

use App\Http\Controllers\StaffAuthController;
use App\Http\Middleware\StaffOrigin;
use App\Http\Middleware\StaffPermission;
use App\Http\Middleware\StaffSession;
use App\Security\StaffPermissions;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/staff')->middleware(['web', StaffOrigin::class])->group(function () {
    Route::get('/csrf', [StaffAuthController::class, 'csrf'])->middleware('throttle:staff-csrf');
    Route::post('/login', [StaffAuthController::class, 'login'])->middleware('throttle:staff-login');
    Route::get('/enrollment', [StaffAuthController::class, 'enrollment'])->middleware('throttle:staff-mfa');
    Route::post('/two-factor', [StaffAuthController::class, 'challenge'])->middleware('throttle:staff-mfa');
    Route::post('/forgot-password', [StaffAuthController::class, 'forgot'])->middleware('throttle:staff-reset');
    Route::post('/reset-password', [StaffAuthController::class, 'reset'])->middleware('throttle:staff-reset');
    Route::middleware(StaffSession::class)->group(function () {
        Route::get('/me', [StaffAuthController::class, 'me']);
        Route::post('/logout', [StaffAuthController::class, 'logout']);
        Route::post('/recovery-codes', [StaffAuthController::class, 'recoveryCodes'])->middleware('throttle:staff-sensitive');
        Route::get('/capabilities/{permission}', [StaffAuthController::class, 'capability'])->whereIn('permission', StaffPermissions::ALL)->middleware(StaffPermission::class);
        Route::get('/audit-events', [StaffAuthController::class, 'audit'])->middleware(StaffPermission::class.':audit.view');
    });
});
