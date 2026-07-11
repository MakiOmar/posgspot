<?php

namespace App\Services\Storefront;

use App\Product;
use App\Services\Storefront\Accounts\AccountsApiClient;
use App\Variation;

/**
 * Proxies Accounts digital catalog and maps lines to configured POS product/variation IDs.
 */
class DigitalCatalogService
{
    public function __construct(
        private AccountsApiClient $accounts,
        private StorefrontSettingService $settings
    ) {
    }

    public function isEnabled(int $businessId): bool
    {
        $digital = $this->settings->get($businessId)['digital'] ?? [];

        return ! empty($digital['enabled']);
    }

    /**
     * @return array{primary:?array,secondary:?array,gift_card:?array}
     */
    public function posSkuMap(int $businessId): array
    {
        $digital = $this->settings->get($businessId)['digital'] ?? [];

        return [
            'primary' => $this->resolveSku((int) ($digital['primary_product_id'] ?? 0), $businessId),
            'secondary' => $this->resolveSku((int) ($digital['secondary_product_id'] ?? 0), $businessId),
            'gift_card' => $this->resolveSku((int) ($digital['gift_card_product_id'] ?? 0), $businessId),
        ];
    }

    public function listGames(int $businessId, string $platform, int $page = 1): array
    {
        $result = $this->accounts->getGamesByPlatform($platform, $page);
        if (! $result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'Failed to load games', 'status' => $result['status']];
        }

        $body = $result['body'] ?? [];
        $skus = $this->posSkuMap($businessId);
        $rawGames = $body['data'] ?? [];
        if (! is_array($rawGames)) {
            $rawGames = [];
        }

        $games = array_map(fn ($game) => $this->normalizeGameListItem($game), $rawGames);

        return [
            'success' => true,
            'data' => [
                'platform' => $platform,
                'skus' => $skus,
                'games' => $games,
                'meta' => [
                    'current_page' => $body['current_page'] ?? $page,
                    'last_page' => $body['last_page'] ?? 1,
                    'per_page' => $body['per_page'] ?? 20,
                    'total' => $body['total'] ?? count($games),
                ],
            ],
        ];
    }

    public function getGame(int $businessId, int $gameId): array
    {
        $result = $this->accounts->getGame($gameId);
        if (! $result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'Game not found', 'status' => $result['status'] ?: 404];
        }

        $body = $result['body'] ?? [];
        $game = $body['data'] ?? $body;

        return [
            'success' => true,
            'data' => [
                'game' => $game,
                'skus' => $this->posSkuMap($businessId),
            ],
        ];
    }

    public function listCardCategories(int $businessId): array
    {
        $result = $this->accounts->getCardCategories();
        if (! $result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'Failed to load gift cards', 'status' => $result['status']];
        }

        $body = $result['body'] ?? [];
        $categories = $body['data'] ?? $body;
        if (! is_array($categories)) {
            $categories = [];
        }

        // Public payload: omit nested card stock rows (browser never needs them).
        $normalized = [];
        foreach ($categories as $category) {
            if (is_object($category)) {
                $category = (array) $category;
            }
            if (! is_array($category)) {
                continue;
            }
            $normalized[] = [
                'id' => (int) ($category['id'] ?? 0),
                'name' => (string) ($category['name'] ?? ''),
                'price' => $category['price'] ?? 0,
                'poster_image' => $category['poster_image'] ?? null,
            ];
        }

        return [
            'success' => true,
            'data' => [
                'categories' => $normalized,
                'skus' => $this->posSkuMap($businessId),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function checkGameStock(array $payload): array
    {
        return $this->accounts->checkStock($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function checkCardStock(array $payload): array
    {
        return $this->accounts->checkCardStock($payload);
    }

    /**
     * @return array{product_id:int,variation_id:int,name:string,image_url:?string}|null
     */
    private function resolveSku(int $productId, int $businessId): ?array
    {
        if ($productId <= 0) {
            return null;
        }

        $product = Product::where('business_id', $businessId)
            ->where('id', $productId)
            ->where('is_inactive', 0)
            ->first();
        if (! $product) {
            return null;
        }

        $variation = Variation::where('product_id', $product->id)->whereNull('deleted_at')->first();
        if (! $variation) {
            return null;
        }

        return [
            'product_id' => (int) $product->id,
            'variation_id' => (int) $variation->id,
            'name' => (string) $product->name,
            'image_url' => $product->image_url ?? null,
        ];
    }

    /**
     * Normalize Accounts list item (`types.primary|secondary`) for the storefront.
     *
     * @param  mixed  $game
     * @return array<string, mixed>
     */
    private function normalizeGameListItem($game): array
    {
        if (! is_array($game)) {
            $game = (array) $game;
        }

        $types = $game['types'] ?? [];
        $primary = is_array($types['primary'] ?? null) ? $types['primary'] : [];
        $secondary = is_array($types['secondary'] ?? null) ? $types['secondary'] : [];

        return [
            'id' => (int) ($game['id'] ?? 0),
            'title' => (string) ($game['title'] ?? ''),
            'code' => $game['code'] ?? null,
            'image_url' => $game['image_url'] ?? null,
            'primary_price' => $primary['price'] ?? ($game['primary_price'] ?? null),
            'secondary_price' => $secondary['price'] ?? ($game['secondary_price'] ?? null),
            'primary_status' => ($primary['available'] ?? false) || ($game['primary_status'] ?? false),
            'secondary_status' => ($secondary['available'] ?? false) || ($game['secondary_status'] ?? false),
            'total_primary_stock' => $primary['stock'] ?? ($game['total_primary_stock'] ?? 0),
            'total_secondary_stock' => $secondary['stock'] ?? ($game['total_secondary_stock'] ?? 0),
            'types' => $types,
        ];
    }
}
