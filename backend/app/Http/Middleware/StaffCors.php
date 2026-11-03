<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class StaffCors
{
    public function handle(Request $request, Closure $next)
    {
        $staff = $request->is('api/v1/staff/*');
        config(['cors.allowed_methods' => $staff ? ['GET', 'POST', 'OPTIONS'] : ['GET', 'OPTIONS'],
            'cors.allowed_origins' => $staff ? [config('staff.admin_origin')] : array_filter(explode(',', config('elvarqo.public_origins'))),
            'cors.allowed_headers' => $staff ? ['Accept', 'Content-Type', 'X-CSRF-TOKEN'] : ['Accept', 'Content-Type'], 'cors.supports_credentials' => $staff]);

        $response = $next($request);
        if ($staff) {
            $response->setVary('Origin', false);
            if ($request->header('Origin') && $request->header('Origin') !== config('staff.admin_origin')) {
                $response->headers->remove('Access-Control-Allow-Origin');
                $response->headers->remove('Access-Control-Allow-Credentials');
            }
        }

        return $response;
    }
}
