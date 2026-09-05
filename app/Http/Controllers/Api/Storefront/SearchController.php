<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\CatalogService;
use App\Services\Storefront\DigitalCatalogService;
use App\Support\StorefrontLocale;
use Illuminate\Http\Request;

class SearchController extends StorefrontController
{
    public function __construct(
        private CatalogService $catalog,
        private DigitalCatalogService $digital
    ) {
    }

    public function index(Request $request)
    {
        $q = (string) $request->query('q', '');
        $limit = min(20, max(1, (int) $request->query('limit', 8)));
        $type = (string) $request->query('type', 'products');
        if (! in_array($type, ['products', 'games', 'gift_cards'], true)) {
            $type = 'products';
        }

        $locale = StorefrontLocale::fromRequest($request);
        $businessId = $this->businessId($request);

        if ($type === 'games') {
            $maxPages = $limit > 8 ? 3 : 1;

            return $this->jsonSuccess($this->digital->searchGames($businessId, $q, $limit, $maxPages));
        }

        if ($type === 'gift_cards') {
            return $this->jsonSuccess($this->digital->searchGiftCards($businessId, $q, $limit));
        }

        $products = $this->catalog->search($businessId, $q, $limit, $locale);

        return $this->jsonSuccess(array_map(function (array $product) {
            $product['kind'] = 'product';
            $product['href'] = '/products/'.($product['slug'] ?: $product['id']);

            return $product;
        }, $products));
    }
}
