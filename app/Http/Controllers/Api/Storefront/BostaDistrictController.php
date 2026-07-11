<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\Shipping\Carriers\BostaApiClient;
use App\Support\StorefrontLocale;
use Illuminate\Http\Request;

/**
 * Bosta districts for a governorate (state code), when courier is enabled.
 */
class BostaDistrictController extends StorefrontController
{
    public function __construct(private BostaApiClient $bosta)
    {
    }

    public function index(Request $request)
    {
        $state = strtoupper(trim((string) $request->query('state', '')));
        if ($state === '') {
            return $this->jsonError('state is required', 422, [
                'state' => ['The state field is required.'],
            ]);
        }

        $businessId = $this->businessId($request);
        if (! $this->bosta->isConfigured($businessId)) {
            return $this->jsonSuccess([
                'city_code' => null,
                'city_name' => null,
                'districts' => [],
            ]);
        }

        $locale = (string) $request->attributes->get('storefront_content_locale', StorefrontLocale::DEFAULT);
        $city = $this->bosta->cityByStateCode($businessId, $state, $locale);
        if (! $city) {
            return $this->jsonSuccess([
                'city_code' => $state,
                'city_name' => $state,
                'districts' => [],
            ]);
        }

        return $this->jsonSuccess($city);
    }
}
