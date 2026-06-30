<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\CatalogService;
use Illuminate\Http\Request;

class ProductController extends StorefrontController
{
    public function __construct(private CatalogService $catalog)
    {
    }

    public function index(Request $request)
    {
        $filters = [
            'category_id' => $request->query('category_id'),
            'category_slug' => $request->query('category_slug'),
            'brand_id' => $request->query('brand_id'),
            'q' => $request->query('q'),
            'sort' => $request->query('sort', 'name'),
            'in_stock_only' => $request->boolean('in_stock_only'),
        ];

        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $paginator = $this->catalog->listProducts($this->businessId($request), $filters, $perPage);

        return $this->jsonSuccess($paginator->items(), [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function show(Request $request, string $idOrSlug)
    {
        $product = $this->catalog->findProduct($this->businessId($request), $idOrSlug);

        if (empty($product)) {
            return $this->jsonError('Product not found.', 404);
        }

        return $this->jsonSuccess($product);
    }
}
