<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function meta(): array
    {
        return ['request_id' => request()->attributes->get('request_id'), 'timestamp' => now()->toISOString()];
    }

    public static function error(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return response()->json(['error' => compact('code', 'message', 'details'), 'meta' => self::meta()], $status);
    }
}
