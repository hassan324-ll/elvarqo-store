<?php

use App\Http\Middleware\RequestContext;
use App\Http\Middleware\StaffCookies;
use App\Http\Middleware\StaffCors;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(StaffCors::class);
        $middleware->prepend(RequestContext::class);
        $middleware->prepend(StaffCookies::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password', 'password_confirmation', 'code', 'recovery_code', 'token', 'message', 'body', 'address', 'billing_address', 'email']);
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            if ($exception instanceof HttpResponseException) {
                return $exception->getResponse();
            }
            if ($exception instanceof UniqueConstraintViolationException) {
                return ApiResponse::error('CONFLICT', 'A unique identifier was just taken. Reload and use a different SKU, barcode or slug.', 409);
            }
            if ($exception instanceof ValidationException) {
                return ApiResponse::error('VALIDATION_FAILED', 'Please check the submitted fields.', 422, $exception->errors());
            }
            $status = $exception instanceof AuthenticationException ? 401 : ($exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500);

            return ApiResponse::error('HTTP_'.$status, $status >= 500 ? 'An unexpected server error occurred.' : 'The request could not be completed.', $status);
        });
    })->create();
