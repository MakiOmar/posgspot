<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\CartValidationService;
use Illuminate\Http\Request;

class CartController extends StorefrontController
{
    public function __construct(private CartValidationService $cartValidation)
    {
    }

    public function validateCart(Request $request)
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.variation_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'location_id' => 'nullable|integer',
        ]);

        $result = $this->cartValidation->validate(
            $this->businessId($request),
            $data['items'],
            $data['location_id'] ?? null
        );

        unset($result['products_payload']);

        return $this->jsonSuccess($result);
    }
}
