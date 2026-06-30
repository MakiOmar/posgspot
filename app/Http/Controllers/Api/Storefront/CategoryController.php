<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\CatalogService;
use Illuminate\Http\Request;

class CategoryController extends StorefrontController
{
    public function __construct(private CatalogService $catalog)
    {
    }

    public function index(Request $request)
    {
        return $this->jsonSuccess($this->catalog->getCategories($this->businessId($request)));
    }
}
