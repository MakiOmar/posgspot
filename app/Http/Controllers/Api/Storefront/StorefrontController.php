<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Base controller for the public Storefront API.
 * All responses use a consistent { success, data, meta } envelope.
 */
abstract class StorefrontController extends Controller
{
    protected function businessId(Request $request): int
    {
        return (int) $request->attributes->get('storefront_business_id', config('storefront.business_id'));
    }

    protected function jsonSuccess(mixed $data = null, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => (object) $meta,
        ], $status);
    }

    protected function jsonError(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
