<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\CatalogService;
use Illuminate\Http\Request;

class SearchController extends StorefrontController
{
    public function __construct(private CatalogService $catalog)
    {
    }

    public function index(Request $request)
    {
        $q = (string) $request->query('q', '');
        $limit = min(20, max(1, (int) $request->query('limit', 8)));

        return $this->jsonSuccess($this->catalog->search($this->businessId($request), $q, $limit));
    }
}
