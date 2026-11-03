<?php

namespace App\Providers;

use App\Security\StaffAudit;
use App\Support\ApiResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        foreach (['customer-public' => 120, 'after-sales-staff' => 120, 'tracking-public' => 60, 'tracking-link' => 10, 'tracking-redeem' => 20, 'tracking-support' => 10, 'courier-webhook' => 120, 'order-staff' => 120, 'shipment-staff' => 120, 'commerce-public' => 120, 'commerce-create' => 20, 'commerce-checkout' => 20, 'commerce-resume' => 30, 'stripe-webhook' => 300] as $name => $attempts) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute($attempts)->by($name.':'.hash('sha256', (string) $request->ip().':'.(str_ends_with($name, '-staff') ? Auth::guard('staff')->id() : '')))->response(fn () => ApiResponse::error('RATE_LIMITED', 'Too many requests. Please wait a minute and retry.', 429)));
        }

        RateLimiter::for('customer-auth', function (Request $r) {
            $action = $r->path();
            $ip = hash('sha256', (string) $r->ip());
            $identity = hash('sha256', strtolower((string) $r->input('email', $r->bearerToken() ?? 'anonymous')));

            return [Limit::perMinute(30)->by('customer-auth:ip:'.$ip), Limit::perMinute(5)->by('customer-auth:'.$action.':'.$identity.':'.$ip)];
        });

        foreach (['catalogue-read' => 600, 'catalogue-media' => 1800] as $name => $attempts) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute($attempts)->by($name.':'.hash('sha256', $request->ip() ?? ''))->response(fn () => ApiResponse::error('RATE_LIMITED', 'Please wait a moment before trying the catalogue again.', 429)));
        }

        foreach (['staff-login' => 5, 'staff-mfa' => 8, 'staff-reset' => 5, 'staff-sensitive' => 5, 'staff-csrf' => 120] as $name => $attempts) {
            RateLimiter::for($name, function (Request $request) use ($name, $attempts) {
                $deny = function () use ($name) {
                    StaffAudit::record('rate_limited', null, $name, 'denied');

                    return ApiResponse::error('RATE_LIMITED', 'Too many attempts. Wait a minute before trying again.', 429);
                };
                $ip = hash('sha256', $request->ip() ?? '');
                $identity = hash('sha256', strtolower((string) $request->input('email', $request->session()->get('staff_pending_id', Auth::guard('staff')->id() ?? 'anonymous'))));

                if ($name === 'staff-csrf') {
                    return Limit::perMinute($attempts)->by($name.':ip:'.$ip)->response($deny);
                }

                return [Limit::perMinute($attempts * 3)->by($name.':ip:'.$ip)->response($deny), Limit::perMinute($attempts)->by($name.':identity:'.$identity)->response($deny)];
            });
        }
    }
}
