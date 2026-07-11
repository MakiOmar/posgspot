<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\CartValidationService;
use App\Support\StorefrontLocale;
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
            'items.*.digital' => 'nullable|array',
            'items.*.digital.kind' => 'nullable|in:game,card',
            'items.*.digital.game_id' => 'nullable|integer|min:1',
            'items.*.digital.type' => 'nullable|in:primary,secondary',
            'items.*.digital.platform' => 'nullable|in:4,5',
            'items.*.digital.card_category_id' => 'nullable|integer|min:1',
            'items.*.digital.line_key' => 'nullable|string|max:191',
            'items.*.digital.title' => 'nullable|string|max:255',
            'items.*.digital.price' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'location_id' => 'nullable|integer',
            'resolve' => 'sometimes|boolean',
            'coupon_code' => 'nullable|string|max:64',
            'coupon_codes' => 'nullable|array|max:10',
            'coupon_codes.*' => 'string|max:64',
            'shipping_rate_id' => 'nullable|string|max:255',
            'destination' => 'nullable|array',
            'destination.country' => 'nullable|string|max:8',
            'destination.state' => 'nullable|string|max:64',
            'destination.city' => 'nullable|string|max:191',
            'destination.zip_code' => 'nullable|string|max:32',
        ]);

        $contact = Auth::guard('sanctum')->user();
        $locale = StorefrontLocale::fromRequest($request);
        $destination = $data['destination'] ?? null;
        $shippingRateId = $data['shipping_rate_id'] ?? null;

        if (! empty($data['resolve'])) {
            $result = $this->cartValidation->inspect(
                $this->businessId($request),
                $data['items'],
                $data['location_id'] ?? null,
                $data['coupon_code'] ?? null,
                $contact,
                $data['coupon_codes'] ?? null,
                $destination,
                $shippingRateId,
                $locale
            );

            return $this->jsonSuccess($result);
        }

        $result = $this->cartValidation->validate(
            $this->businessId($request),
            $data['items'],
            $data['location_id'] ?? null,
            $data['coupon_code'] ?? null,
            $contact,
            $data['coupon_codes'] ?? null,
            $destination,
            $shippingRateId,
            $locale,
            false
        );

        unset($result['products_payload']);

        return $this->jsonSuccess($result);
    }
}
