<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\WishlistService;
use App\Support\StorefrontLocale;
use Illuminate\Http\Request;

class WishlistController extends StorefrontController
{
    public function __construct(private WishlistService $wishlist)
    {
    }

    public function index(Request $request)
    {
        $locale = StorefrontLocale::fromRequest($request);

        return $this->jsonSuccess(
            $this->wishlist->list($this->businessId($request), (int) $request->user()->id, $locale)
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|integer|min:1',
        ]);
        $locale = StorefrontLocale::fromRequest($request);

        return $this->jsonSuccess(
            $this->wishlist->add(
                $this->businessId($request),
                (int) $request->user()->id,
                (int) $data['product_id'],
                $locale
            )
        );
    }

    public function merge(Request $request)
    {
        $data = $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'integer|min:1',
        ]);
        $locale = StorefrontLocale::fromRequest($request);

        return $this->jsonSuccess(
            $this->wishlist->merge(
                $this->businessId($request),
                (int) $request->user()->id,
                $data['product_ids'],
                $locale
            )
        );
    }

    public function destroy(Request $request, int $productId)
    {
        $locale = StorefrontLocale::fromRequest($request);

        return $this->jsonSuccess(
            $this->wishlist->remove(
                $this->businessId($request),
                (int) $request->user()->id,
                $productId,
                $locale
            )
        );
    }
}
