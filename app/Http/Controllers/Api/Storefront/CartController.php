<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\CartValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            'resolve' => 'sometimes|boolean',
            'coupon_code' => 'nullable|string|max:64',
            'coupon_codes' => 'nullable|array|max:10',
            'coupon_codes.*' => 'string|max:64',
        ]);

        $contact = Auth::guard('sanctum')->user();

        if (! empty($data['resolve'])) {
            $result = $this->cartValidation->inspect(
                $this->businessId($request),
                $data['items'],
                $data['location_id'] ?? null,
                $data['coupon_code'] ?? null,
                $contact,
                $data['coupon_codes'] ?? null
            );

            return $this->jsonSuccess($result);
        }

        $result = $this->cartValidation->validate(
            $this->businessId($request),
            $data['items'],
            $data['location_id'] ?? null,
            $data['coupon_code'] ?? null,
            $contact,
            $data['coupon_codes'] ?? null
        );

        unset($result['products_payload']);

        return $this->jsonSuccess($result);
    }
}
