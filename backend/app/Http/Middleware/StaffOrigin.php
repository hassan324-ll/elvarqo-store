<?php

namespace App\Http\Middleware;

use App\Security\StaffAudit;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;

class StaffOrigin
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->header('Origin') && $request->header('Origin') !== config('staff.admin_origin')) {
            StaffAudit::record('origin.denied', null, 'staff', 'denied');

            return ApiResponse::error('ORIGIN_DENIED', 'This origin cannot access the staff application.', 403);
        }
        // Always require token matching, including same-origin requests and real HTTP tests.
        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) &&
            (! is_string($request->header('X-CSRF-TOKEN')) || ! hash_equals($request->session()->token(), $request->header('X-CSRF-TOKEN')))) {
            StaffAudit::record('csrf.denied', null, 'staff', 'denied');

            return ApiResponse::error('CSRF_EXPIRED', 'Your security session expired. Please retry.', 419);
        }

        return $next($request);
    }
}
