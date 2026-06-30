<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\SettingsApiService;
use Illuminate\Http\Request;

class LocationController extends StorefrontController
{
    public function __construct(private SettingsApiService $settings)
    {
    }

    public function index(Request $request)
    {
        return $this->jsonSuccess($this->settings->getLocations($this->businessId($request)));
    }
}
