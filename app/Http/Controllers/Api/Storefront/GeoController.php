<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\GeoDataService;

class GeoController extends StorefrontController
{
    public function __construct(private GeoDataService $geo)
    {
    }

    public function countries()
    {
        return $this->jsonSuccess($this->geo->getCountries());
    }

    public function states(string $countryCode)
    {
        return $this->jsonSuccess($this->geo->getStates($countryCode));
    }
}
