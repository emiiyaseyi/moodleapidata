<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function respondSuccess(mixed $data, string $message = 'Request successful.', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => [
                'timestamp' => now()->toIso8601String(),
                'version' => 'v1',
            ],
        ], $status);
    }

    protected function respondError(string $message, int $status = 400, ?string $errorCode = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'meta' => [
                'timestamp' => now()->toIso8601String(),
                'version' => 'v1',
                'error_code' => $errorCode,
            ],
        ], $status);
    }
}
