<?php

use App\Http\Controllers\OverviewController;
use App\Http\Middleware\StaffOrigin;
use App\Http\Middleware\StaffSession;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', function () {
    try {
        DB::select('SELECT 1');
    } catch (Throwable $exception) {
        report($exception);

        return ApiResponse::error('SERVICE_UNAVAILABLE', 'The database is unavailable.', 503);
    }

    return response()->json([
        'data' => ['status' => 'ok', 'service' => 'elvarqo-api', 'database' => 'ok'],
        'meta' => ApiResponse::meta(),
    ]);
});

require __DIR__.'/staff.php';

require __DIR__.'/catalogue.php';

require __DIR__.'/commerce.php';

require __DIR__.'/fulfilment.php';

require __DIR__.'/customers.php';

Route::get('v1/staff/overview', OverviewController::class)
    ->middleware(['web', StaffOrigin::class, StaffSession::class, 'throttle:120,1']);
