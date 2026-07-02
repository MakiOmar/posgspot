<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\CatalogService;
use App\Support\StorefrontLocale;
use Illuminate\Http\Request;

class CategoryController extends StorefrontController
{
    public function __construct(private CatalogService $catalog)
    {
    }

    public function index(Request $request)
    {
        $locale = StorefrontLocale::fromRequest($request);

        return $this->jsonSuccess($this->catalog->getCategories($this->businessId($request), $locale));
    }

    public function show(Request $request, string $slug)
    {
        $locale = StorefrontLocale::fromRequest($request);
        $category = $this->catalog->findCategoryBySlug($this->businessId($request), $slug, $locale);

        if (empty($category)) {
            return $this->jsonError('Category not found.', 404);
        }

        return $this->jsonSuccess($category);
    }
}
