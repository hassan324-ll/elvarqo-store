<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CommerceOrigin
{
    public function handle(Request $r, Closure $next)
    {
        if (! $r->isMethod('GET')) {
            abort_unless($r->header('Origin') === config('commerce.storefront_origin') && $r->isJson(), 403);
        }$response = $next($r);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
