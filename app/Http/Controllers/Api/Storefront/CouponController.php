<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Contact;
use App\Services\Storefront\CouponService;
use App\Services\Storefront\StorefrontSettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CouponController extends StorefrontController
{
    public function __construct(
        private CouponService $couponService,
        private StorefrontSettingService $storefrontSettings
    ) {
    }

    public function validateCode(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:64',
            'items' => 'required|array|min:1',
            'items.*.variation_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'location_id' => 'nullable|integer',
            'coupon_codes' => 'nullable|array|max:10',
            'coupon_codes.*' => 'string|max:64',
        ]);

        $businessId = $this->businessId($request);
        /** @var Contact|null $contact */
        $contact = Auth::guard('sanctum')->user();
        $settings = $this->storefrontSettings->get($businessId);
        $built = $this->couponService->buildCouponLines($businessId, $data['items']);
        $codes = $this->couponService->normalizeCodes($data['code'], $data['coupon_codes'] ?? null);

        $applied = $this->couponService->applyMultipleToCart(
            $businessId,
            $codes,
            $built['lines'],
            $built['subtotal'],
            $settings,
            $contact
        );

        return $this->jsonSuccess($applied);
    }

    public function available(Request $request)
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.variation_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'exclude_codes' => 'nullable|array|max:10',
            'exclude_codes.*' => 'string|max:64',
        ]);

        /** @var Contact|null $contact */
        $contact = Auth::guard('sanctum')->user();
        if (empty($contact)) {
            return $this->jsonError('Sign in to view promo codes.', 422, [
                'coupon_code' => ['Sign in to apply a promo code.'],
            ]);
        }

        $businessId = $this->businessId($request);
        $settings = $this->storefrontSettings->get($businessId);
        $built = $this->couponService->buildCouponLines($businessId, $data['items']);

        $coupons = $this->couponService->listAvailableForCart(
            $businessId,
            $built['lines'],
            $built['subtotal'],
            $settings,
            $contact,
            $data['exclude_codes'] ?? []
        );

        return $this->jsonSuccess(['coupons' => $coupons]);
    }
}
