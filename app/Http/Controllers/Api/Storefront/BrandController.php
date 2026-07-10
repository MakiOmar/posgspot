<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\CatalogService;
use App\Support\StorefrontLocale;
use Illuminate\Http\Request;

class BrandController extends StorefrontController
{
    public function __construct(private CatalogService $catalog)
    {
    }

    public function index(Request $request)
    {
        $locale = StorefrontLocale::fromRequest($request);

        return $this->jsonSuccess($this->catalog->getBrands($this->businessId($request), $locale));
    }

    public function show(Request $request, string $slug)
    {
        $locale = StorefrontLocale::fromRequest($request);
        $brand = $this->catalog->findBrandBySlug($this->businessId($request), $slug, $locale);

        if (empty($brand)) {
            return $this->jsonError('Brand not found.', 404);
        }

        return $this->jsonSuccess($brand);
    }
}
