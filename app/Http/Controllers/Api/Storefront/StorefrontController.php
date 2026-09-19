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

    /**
     * Cacheable public GET responses (settings, categories, etc.).
     * Clients / CDNs may reuse for $maxAge seconds; ETag enables 304.
     */
    protected function jsonSuccessPublicCache(
        mixed $data = null,
        array $meta = [],
        int $maxAge = 60,
        int $status = 200
    ): JsonResponse {
        $response = $this->jsonSuccess($data, $meta, $status);
        $payload = json_encode([$data, $meta], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        $etag = '"'.hash('xxh128', $payload).'"';

        $response
            ->setPublic()
            ->setMaxAge(max(1, $maxAge))
            ->setSharedMaxAge(max(1, $maxAge))
            ->setEtag($etag);

        $request = request();
        if ($request instanceof Request) {
            $response->isNotModified($request);
        }

        return $response;
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
