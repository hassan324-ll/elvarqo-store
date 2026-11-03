<?php

namespace App\Http\Middleware;

use App\Security\StaffAudit;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaffPermission
{
    public function handle(Request $request, Closure $next, ?string $permission = null)
    {
        $permission ??= $request->route('permission');
        $staff = Auth::guard('staff')->user();
        if (! $staff || ! $staff->canAccess($permission)) {
            StaffAudit::record('permission.denied', $staff?->id, $permission, 'denied');

            return ApiResponse::error('FORBIDDEN', 'Your staff role does not permit this operation.', 403);
        }

        return $next($request);
    }
}
