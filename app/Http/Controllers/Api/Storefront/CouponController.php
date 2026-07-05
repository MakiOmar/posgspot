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
        ]);

        $businessId = $this->businessId($request);
        /** @var Contact|null $contact */
        $contact = Auth::guard('sanctum')->user();
        $settings = $this->storefrontSettings->get($businessId);
        $built = $this->couponService->buildCouponLines($businessId, $data['items']);

        $applied = $this->couponService->applyToCart(
            $businessId,
            $data['code'],
            $built['lines'],
            $built['subtotal'],
            $settings,
            $contact
        );

        return $this->jsonSuccess($applied);
    }
}
