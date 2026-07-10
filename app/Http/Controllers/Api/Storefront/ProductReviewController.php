<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\ProductReviewService;
use App\Support\StorefrontLocale;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductReviewController extends StorefrontController
{
    public function __construct(private ProductReviewService $reviews)
    {
    }

    public function index(Request $request, string $idOrSlug)
    {
        $businessId = $this->businessId($request);
        $locale = StorefrontLocale::fromRequest($request);
        $productId = $this->reviews->resolveProductId($businessId, $idOrSlug, $locale);
        if ($productId === null) {
            return $this->jsonError('Product not found.', 404);
        }

        $perPage = min(50, max(1, (int) $request->query('per_page', 10)));
        $paginator = $this->reviews->listApproved($businessId, $productId, $perPage);

        return $this->jsonSuccess($paginator->items(), [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function eligibility(Request $request, string $idOrSlug)
    {
        $businessId = $this->businessId($request);
        $locale = StorefrontLocale::fromRequest($request);
        $productId = $this->reviews->resolveProductId($businessId, $idOrSlug, $locale);
        if ($productId === null) {
            return $this->jsonError('Product not found.', 404);
        }

        return $this->jsonSuccess(
            $this->reviews->eligibility($businessId, $request->user(), $productId)
        );
    }

    public function store(Request $request, string $idOrSlug)
    {
        $businessId = $this->businessId($request);
        $locale = StorefrontLocale::fromRequest($request);
        $productId = $this->reviews->resolveProductId($businessId, $idOrSlug, $locale);
        if ($productId === null) {
            return $this->jsonError('Product not found.', 404);
        }

        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:120',
            'body' => 'required|string|min:10|max:2000',
        ]);

        $result = $this->reviews->submit($businessId, $request->user(), $productId, $data);

        return $this->jsonSuccess($result, [], 201);
    }
}
