<?php

namespace App\Http\Middleware;

use App\Security\StaffAudit;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaffSession
{
    public function handle(Request $request, Closure $next)
    {
        $staff = Auth::guard('staff')->user();
        if (! $staff) {
            return ApiResponse::error('UNAUTHENTICATED', 'Sign in to the staff application.', 401);
        }
        $now = now()->timestamp;
        if (! $staff->active || ! $staff->two_factor_confirmed_at || $request->session()->get('staff_version') !== $staff->auth_version ||
            $now - $request->session()->get('staff_authenticated_at', 0) > config('staff.absolute_seconds') ||
            $now - $request->session()->get('staff_seen_at', 0) > config('staff.idle_seconds')) {
            StaffAudit::record('session.expired', $staff->id, 'staff:'.$staff->id, 'denied');
            Auth::guard('staff')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return ApiResponse::error('SESSION_EXPIRED', 'Your staff session expired. Sign in again.', 401);
        }
        $request->session()->put('staff_seen_at', $now);

        return $next($request);
    }
}
