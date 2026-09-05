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

    public function listGames(int $businessId, string $platform, int $page = 1, ?string $q = null): array
    {
        $term = trim((string) $q);
        $path = 'api/games/platform/'.$platform;
        $accountsBase = $this->accounts->baseUrl();
        $requestUrl = ($accountsBase !== '' ? $accountsBase.'/' : '').$path.'?page='.$page;
        if ($term !== '') {
            $requestUrl .= '&q='.rawurlencode($term);
        }
        $skus = $this->posSkuMap($businessId);

        $result = $this->accounts->getGamesByPlatform($platform, $page, $term !== '' ? $term : null);
        $body = is_array($result['body'] ?? null) ? $result['body'] : [];
        $rawGames = $body['data'] ?? [];
        if (! is_array($rawGames)) {
            $rawGames = [];
        }

        $games = $result['success']
            ? array_map(fn ($game) => $this->normalizeGameListItem($game), $rawGames)
            : [];

        if ($term !== '') {
            $games = array_values(array_filter(
                $games,
                fn ($game) => $this->matchesSearchTerm(
                    [(string) ($game['title'] ?? ''), (string) ($game['code'] ?? '')],
                    $term
                )
            ));
        }

        $reason = $this->emptyGamesReason(
            $accountsBase,
            $result['success'],
            (int) ($result['status'] ?? 0),
            $result['error'] ?? null,
            count($rawGames),
            count($games),
            $skus
        );

        $debug = [
            'accounts_base' => $accountsBase !== '' ? $accountsBase : '(empty — set ACCOUNTS_BASE_URL)',
            'request_method' => 'GET',
            'request_path' => $path,
            'request_url' => $requestUrl,
            'platform' => $platform,
            'page' => $page,
            'http_status' => (int) ($result['status'] ?? 0),
            'accounts_ok' => (bool) $result['success'],
            'error' => $result['error'] ?? null,
            'body_keys' => array_values(array_map('strval', array_keys($body))),
            'raw_item_count' => count($rawGames),
            'normalized_count' => count($games),
            'paginator_total' => $body['total'] ?? null,
            'skus' => [
                'primary' => $skus['primary']['variation_id'] ?? null,
                'secondary' => $skus['secondary']['variation_id'] ?? null,
            ],
            'reason' => $reason,
        ];

        if (! $result['success']) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Failed to load games',
                'status' => $result['status'] ?: 502,
                'debug' => $debug,
            ];
        }

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
                'debug' => $debug,
            ],
        ];
    }

    /**
     * Autocomplete / search hits for PS4 + PS5 digital games.
     *
     * @return list<array<string, mixed>>
     */
    public function searchGames(int $businessId, string $q, int $limit = 8, int $maxPages = 1): array
    {
        $term = trim($q);
        if ($term === '' || ! $this->isEnabled($businessId)) {
            return [];
        }

        $limit = max(1, $limit);
        $maxPages = max(1, $maxPages);
        $hits = [];

        foreach (['4', '5'] as $platform) {
            for ($page = 1; $page <= $maxPages; $page++) {
                $result = $this->listGames($businessId, $platform, $page, $term);
                if (empty($result['success'])) {
                    break;
                }

                $games = is_array($result['data']['games'] ?? null) ? $result['data']['games'] : [];
                foreach ($games as $game) {
                    if (! is_array($game)) {
                        continue;
                    }
                    $hits[] = $this->formatGameSearchHit($game, $platform);
                    if (count($hits) >= $limit) {
                        return $hits;
                    }
                }

                $lastPage = (int) ($result['data']['meta']['last_page'] ?? 1);
                if ($page >= $lastPage) {
                    break;
                }
            }
        }

        return $hits;
    }

    /**
     * Autocomplete / search hits for gift card categories.
     *
     * @return list<array<string, mixed>>
     */
    public function searchGiftCards(int $businessId, string $q, int $limit = 8): array
    {
        $term = trim($q);
        if ($term === '' || ! $this->isEnabled($businessId)) {
            return [];
        }

        $result = $this->listCardCategories($businessId);
        if (empty($result['success'])) {
            return [];
        }

        $categories = is_array($result['data']['categories'] ?? null) ? $result['data']['categories'] : [];
        $hits = [];
        foreach ($categories as $category) {
            if (! is_array($category)) {
                continue;
            }
            $name = (string) ($category['name'] ?? '');
            if (! $this->matchesSearchTerm([$name], $term)) {
                continue;
            }
            $hits[] = $this->formatGiftCardSearchHit($category);
            if (count($hits) >= $limit) {
                break;
            }
        }

        return $hits;
    }

    /**
     * @param  array{primary:?array,secondary:?array,gift_card:?array}  $skus
     */
    private function emptyGamesReason(
        string $accountsBase,
        bool $ok,
        int $httpStatus,
        ?string $error,
        int $rawCount,
        int $normalizedCount,
        array $skus
    ): string {
        if ($accountsBase === '') {
            return 'ACCOUNTS_BASE_URL is empty — POS cannot reach the Accounts API.';
        }
        if (! $ok) {
            return 'Accounts request failed (HTTP '.$httpStatus.'): '.($error ?: 'unknown error');
        }
        if ($rawCount === 0) {
            return 'Accounts returned an empty list for this platform/page (no stocked games, or wrong Accounts base URL).';
        }
        if ($normalizedCount === 0) {
            return 'Accounts returned items but none could be normalized for the storefront.';
        }
        if (empty($skus['primary']) && empty($skus['secondary'])) {
            return 'Games listed, but POS digital product IDs are not configured (add-to-cart will fail).';
        }

        return 'ok';
    }

    public function getGame(int $businessId, int $gameId): array
    {
        $result = $this->accounts->getGame($gameId);
        if (! $result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'Game not found', 'status' => $result['status'] ?: 404];
        }

        $body = $result['body'] ?? [];
        $game = $body['data'] ?? $body;
        if (is_object($game)) {
            $game = (array) $game;
        }
        if (is_array($game)) {
            foreach (['image_url', 'ps4_image_url', 'ps5_image_url'] as $imageKey) {
                if (! empty($game[$imageKey])) {
                    $game[$imageKey] = $this->absoluteAccountsUrl((string) $game[$imageKey]);
                }
            }
        }

        return [
            'success' => true,
            'data' => [
                'game' => $game,
                'skus' => $this->posSkuMap($businessId),
            ],
        ];
    }

    /**
     * Resolve Accounts catalog unit price for a digital cart line (same source as send-to-POS order.price).
     *
     * @param  array<string, mixed>  $digital
     */
    public function resolveOfferPrice(int $businessId, array $digital): ?float
    {
        $kind = (string) ($digital['kind'] ?? '');
        if ($kind === 'game') {
            $gameId = (int) ($digital['game_id'] ?? 0);
            if ($gameId <= 0) {
                return null;
            }
            $type = (string) ($digital['type'] ?? 'primary');
            $platform = (string) ($digital['platform'] ?? '4');
            if (! in_array($type, ['primary', 'secondary'], true)) {
                $type = 'primary';
            }
            if (! in_array($platform, ['4', '5'], true)) {
                $platform = '4';
            }

            $result = $this->getGame($businessId, $gameId);
            if (! $result['success']) {
                return null;
            }
            $game = is_array($result['data']['game'] ?? null) ? $result['data']['game'] : [];
            $priceKey = "ps{$platform}_{$type}_price";
            $fallbackKey = $type === 'primary' ? 'primary_price' : 'secondary_price';
            $ps4Key = $type === 'primary' ? 'ps4_primary_price' : 'ps4_secondary_price';
            foreach ([$game[$priceKey] ?? null, $game[$fallbackKey] ?? null, $game[$ps4Key] ?? null] as $candidate) {
                if ($candidate !== null && $candidate !== '' && is_numeric($candidate) && (float) $candidate > 0) {
                    return (float) $candidate;
                }
            }

            return null;
        }

        if ($kind === 'card') {
            $categoryId = (int) ($digital['card_category_id'] ?? 0);
            if ($categoryId <= 0) {
                return null;
            }
            $result = $this->listCardCategories($businessId);
            if (! $result['success']) {
                return null;
            }
            $categories = is_array($result['data']['categories'] ?? null) ? $result['data']['categories'] : [];
            foreach ($categories as $category) {
                if (! is_array($category)) {
                    continue;
                }
                if ((int) ($category['id'] ?? 0) !== $categoryId) {
                    continue;
                }
                $price = $category['price'] ?? null;
                if ($price !== null && $price !== '' && is_numeric($price) && (float) $price > 0) {
                    return (float) $price;
                }
            }
        }

        return null;
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
                'poster_image' => $this->absoluteAccountsUrl(
                    isset($category['poster_image']) ? (string) $category['poster_image'] : null
                ),
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
            'image_url' => $this->absoluteAccountsUrl(
                isset($game['image_url']) ? (string) $game['image_url'] : null
            ),
            'primary_price' => $primary['price'] ?? ($game['primary_price'] ?? null),
            'secondary_price' => $secondary['price'] ?? ($game['secondary_price'] ?? null),
            'primary_status' => ($primary['available'] ?? false) || ($game['primary_status'] ?? false),
            'secondary_status' => ($secondary['available'] ?? false) || ($game['secondary_status'] ?? false),
            'total_primary_stock' => $primary['stock'] ?? ($game['total_primary_stock'] ?? 0),
            'total_secondary_stock' => $secondary['stock'] ?? ($game['total_secondary_stock'] ?? 0),
            'types' => $types,
        ];
    }

    /**
     * @param  array<string, mixed>  $game
     * @return array<string, mixed>
     */
    private function formatGameSearchHit(array $game, string $platform): array
    {
        $price = 0.0;
        foreach ([$game['primary_price'] ?? null, $game['secondary_price'] ?? null] as $candidate) {
            if ($candidate !== null && $candidate !== '' && is_numeric($candidate) && (float) $candidate > 0) {
                $price = (float) $candidate;
                break;
            }
        }

        $id = (int) ($game['id'] ?? 0);

        return [
            'id' => $id,
            'name' => (string) ($game['title'] ?? ''),
            'slug' => null,
            'sku' => (string) ($game['code'] ?? ''),
            'type' => 'digital_game',
            'image_url' => $game['image_url'] ?? null,
            'variation_id' => null,
            'variation_name' => 'PS'.$platform,
            'has_options' => false,
            'price' => $price,
            'compare_at_price' => null,
            'on_sale' => false,
            'sale_percent' => 0,
            'in_stock' => ! empty($game['primary_status']) || ! empty($game['secondary_status']),
            'kind' => 'game',
            'href' => '/games/'.$id.'?platform='.$platform,
            'platform' => $platform,
        ];
    }

    /**
     * @param  array<string, mixed>  $category
     * @return array<string, mixed>
     */
    private function formatGiftCardSearchHit(array $category): array
    {
        $id = (int) ($category['id'] ?? 0);
        $name = (string) ($category['name'] ?? '');

        return [
            'id' => $id,
            'name' => $name,
            'slug' => null,
            'sku' => '',
            'type' => 'gift_card',
            'image_url' => $category['poster_image'] ?? null,
            'variation_id' => null,
            'variation_name' => null,
            'has_options' => false,
            'price' => is_numeric($category['price'] ?? null) ? (float) $category['price'] : 0,
            'compare_at_price' => null,
            'on_sale' => false,
            'sale_percent' => 0,
            'in_stock' => true,
            'kind' => 'gift_card',
            'href' => '/gift-cards?q='.rawurlencode($name),
        ];
    }

    /**
     * @param  list<string>  $fields
     */
    private function matchesSearchTerm(array $fields, string $term): bool
    {
        foreach ($fields as $field) {
            if ($field !== '' && mb_stripos($field, $term) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Turn Accounts-relative asset paths into absolute URLs for the browser.
     */
    private function absoluteAccountsUrl(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }
        $path = trim($path);
        if ($path === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $base = $this->accounts->baseUrl();
        if ($base === '') {
            return $path;
        }

        // Encode path segments (Accounts paths often include spaces).
        $trimmed = ltrim(str_replace('\\', '/', $path), '/');
        $encoded = implode('/', array_map('rawurlencode', explode('/', $trimmed)));

        return $base.'/'.$encoded;
    }
}
