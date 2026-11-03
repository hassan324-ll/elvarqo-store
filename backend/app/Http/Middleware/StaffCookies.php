<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaffCookies
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('api/v1/staff/*')) {
            config(['session.cookie' => 'elvarqo_staff_session', 'session.path' => '/api/v1/staff', 'session.table' => 'staff_sessions',
                'session.lifetime' => 30, 'session.expire_on_close' => true, 'session.same_site' => 'strict', 'session.domain' => null,
                'session.secure' => ! app()->environment(['local', 'testing']), 'session.http_only' => true]);
            Auth::shouldUse('staff');
        }

        return $next($request);
    }
}
