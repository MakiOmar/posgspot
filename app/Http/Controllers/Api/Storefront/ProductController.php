<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\CatalogService;
use App\Support\StorefrontLocale;
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
            'brand_slug' => $request->query('brand_slug'),
            'q' => $request->query('q'),
            'sort' => $request->query('sort', 'default'),
            'in_stock_only' => $request->boolean('in_stock_only'),
            'featured' => $request->boolean('featured'),
            'ids' => $this->parseIds($request->query('ids')),
        ];

        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $locale = StorefrontLocale::fromRequest($request);
        $paginator = $this->catalog->listProducts($this->businessId($request), $filters, $perPage, $locale);

        return $this->jsonSuccess($paginator->items(), [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function show(Request $request, string $idOrSlug)
    {
        $locale = StorefrontLocale::fromRequest($request);
        $product = $this->catalog->findProduct($this->businessId($request), $idOrSlug, null, $locale);

        if (empty($product)) {
            return $this->jsonError('Product not found.', 404);
        }

        return $this->jsonSuccess($product);
    }

    /**
     * `ids=12,5,9` (or `ids[]=12`) → ordered unique ints, max 50 (matches per_page cap).
     *
     * @return list<int>
     */
    private function parseIds(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $parts = is_array($raw) ? $raw : explode(',', (string) $raw);

        $ids = [];
        foreach ($parts as $part) {
            $id = (int) trim((string) $part);
            if ($id > 0 && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return array_slice($ids, 0, 50);
    }
}
