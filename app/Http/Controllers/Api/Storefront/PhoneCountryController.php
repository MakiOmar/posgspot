<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\PhoneValidationService;

class PhoneCountryController extends StorefrontController
{
    public function __construct(private PhoneValidationService $phoneValidation)
    {
    }

    public function index()
    {
        $countries = $this->phoneValidation->getCountriesData();

        usort($countries, fn ($a, $b) => strcmp($a['name_en'] ?? '', $b['name_en'] ?? ''));

        return $this->jsonSuccess($countries);
    }
}
