<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiError
{
    public static function response(int $status, string $message, Request $request, ?array $errors = null, array $headers = []): JsonResponse
    {
        $body = [
            'timestamp' => now()->toIso8601String(),
            'status' => $status,
            'error' => Response::$statusTexts[$status] ?? 'Error',
            'message' => $message,
            'path' => '/'.ltrim($request->path(), '/'),
            'correlationId' => $request->attributes->get('correlation_id'),
        ];

        if ($errors) {
            $body['errors'] = $errors; // detalle por campo en los 422
        }

        return response()->json($body, $status, $headers);
    }
}
