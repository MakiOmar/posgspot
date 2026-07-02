<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\SettingsApiService;
use App\Support\StorefrontLocale;
use Illuminate\Http\Request;

class SettingsController extends StorefrontController
{
    public function __construct(private SettingsApiService $settings)
    {
    }

    public function show(Request $request)
    {
        $locale = StorefrontLocale::fromRequest($request);

        return $this->jsonSuccess($this->settings->getPublicSettings($this->businessId($request), $locale));
    }
}
