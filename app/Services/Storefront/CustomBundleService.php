<?php

namespace App\Services\Storefront;

use App\Category;
use App\Support\StorefrontLocale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Custom Bundle builder: physical catalog only (no digital games / gift cards).
 * Tabs are real POS categories under the selected platform (plus All).
 * Shared by Qwik and future Expo clients via GET /custom-bundle/*.
 */
class CustomBundleService
{
    public const PLATFORMS = ['ps5', 'ps4'];

    public function __construct(private CatalogService $catalog)
    {
    }

    public function isEnabled(): bool
    {
        return (bool) config('storefront.custom_bundle.enabled', false);
    }

    public function minItems(): int
    {
        $min = (int) config('storefront.custom_bundle.min_items', 2);

        return max(1, $min);
    }

    public function maxItems(): int
    {
        $max = (int) config('storefront.custom_bundle.max_items', 15);
        $min = $this->minItems();

        return max($min, $max);
    }

    /**
     * @return array{
     *   enabled: bool,
     *   min_items: int,
     *   max_items: int,
     *   platforms: list<array{id: string, label: string}>,
     *   tabs: list<array{id: string, label: string}>
     * }
     */
    public function meta(
        int $businessId,
        string $locale = StorefrontLocale::DEFAULT,
        ?string $platform = null
    ): array {
        $ar = $locale === 'ar';
        $platform = $platform !== null ? strtolower(trim($platform)) : null;
        if ($platform !== null && ! in_array($platform, self::PLATFORMS, true)) {
            $platform = null;
        }

        $tabs = [
            ['id' => 'all', 'label' => $ar ? 'الكل' : 'All'],
        ];
        if ($platform !== null) {
            foreach ($this->categoryTabsForPlatform($businessId, $platform, $locale) as $tab) {
                $tabs[] = $tab;
            }
        }

        return [
            'enabled' => $this->isEnabled(),
            'min_items' => $this->minItems(),
            'max_items' => $this->maxItems(),
            'platforms' => [
                ['id' => 'ps5', 'label' => 'PS5'],
                ['id' => 'ps4', 'label' => 'PS4'],
            ],
            'tabs' => $tabs,
        ];
    }

    /**
     * @param  array{platform?: string, tab?: string, q?: string}  $filters
     */
    public function listProducts(
        int $businessId,
        array $filters = [],
        int $perPage = 20,
        string $locale = StorefrontLocale::DEFAULT
    ): LengthAwarePaginator {
        $platform = strtolower(trim((string) ($filters['platform'] ?? '')));
        $tab = strtolower(trim((string) ($filters['tab'] ?? 'all')));
        if (! in_array($platform, self::PLATFORMS, true)) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
        if (! $this->isValidTab($tab)) {
            $tab = 'all';
        }

        $tree = $this->categoryTree($businessId, $locale);
        $digitalIds = $this->collectDigitalCategoryIds($tree);
        $categoryIds = $this->resolveCategoryIds($tree, $platform, $tab, $digitalIds);

        if ($categoryIds === []) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        return $this->catalog->listProducts(
            $businessId,
            [
                'category_ids' => $categoryIds,
                'exclude_category_ids' => $digitalIds,
                'q' => $filters['q'] ?? null,
                'in_stock_only' => true,
                'sort' => 'default',
            ],
            $perPage,
            $locale
        );
    }

    /**
     * Top-level browse tabs: All is separate; here one tab per category under the platform.
     *
     * @return list<array{id: string, label: string}>
     */
    private function categoryTabsForPlatform(int $businessId, string $platform, string $locale): array
    {
        $tree = $this->categoryTree($businessId, $locale);
        $digitalLookup = array_fill_keys($this->collectDigitalCategoryIds($tree), true);
        $platformNodes = $this->findPlatformNodes($tree, $platform);

        $tabNodes = [];
        if ($platformNodes !== []) {
            // Prefer direct children of the matched PS4/PS5 category(ies).
            foreach ($platformNodes as $node) {
                $subs = $node['sub_categories'] ?? [];
                if (is_array($subs) && $subs !== []) {
                    foreach ($subs as $child) {
                        if (is_array($child) && ! empty($child['id']) && ! isset($digitalLookup[(int) $child['id']])) {
                            $tabNodes[] = $child;
                        }
                    }
                } else {
                    // Platform category is a leaf — still offer it as a tab.
                    if (! isset($digitalLookup[(int) ($node['id'] ?? 0)])) {
                        $tabNodes[] = $node;
                    }
                }
            }
        } else {
            // No PS4/PS5 parent found: expose all top-level physical categories.
            foreach ($tree as $node) {
                if (! is_array($node) || empty($node['id'])) {
                    continue;
                }
                if (isset($digitalLookup[(int) $node['id']])) {
                    continue;
                }
                $tabNodes[] = $node;
            }
        }

        $tabs = [];
        $seen = [];
        foreach ($tabNodes as $node) {
            $id = (int) ($node['id'] ?? 0);
            if ($id < 1 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $label = trim((string) ($node['name'] ?? ''));
            if ($label === '') {
                $label = 'Category '.$id;
            }
            $tabs[] = [
                'id' => 'cat:'.$id,
                'label' => $label,
            ];
        }

        return $tabs;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function categoryTree(int $businessId, string $locale): array
    {
        $tree = $this->catalog->getCategories($businessId, $locale);
        if ($tree !== []) {
            return $tree;
        }

        // Fallback when locale filter empties the tree (e.g. missing AR translations).
        return Category::catAndSubCategories($businessId);
    }

    private function isValidTab(string $tab): bool
    {
        return $tab === 'all' || (bool) preg_match('/^cat:\d+$/', $tab);
    }

    /**
     * @param  list<array<string, mixed>>  $tree
     * @param  list<int>  $digitalIds
     * @return list<int>
     */
    private function resolveCategoryIds(array $tree, string $platform, string $tab, array $digitalIds): array
    {
        $digitalLookup = array_fill_keys($digitalIds, true);
        $platformNodes = $this->findPlatformNodes($tree, $platform);

        $scope = $platformNodes !== []
            ? $this->flattenCategoryNodes($platformNodes)
            : $this->flattenCategoryNodes($tree);

        $scope = array_values(array_filter(
            $scope,
            fn (array $node) => ! isset($digitalLookup[(int) ($node['id'] ?? 0)])
        ));

        if ($tab === 'all') {
            return array_values(array_unique(array_map(
                fn (array $node) => (int) $node['id'],
                $scope
            )));
        }

        if (preg_match('/^cat:(\d+)$/', $tab, $m)) {
            $targetId = (int) $m[1];
            $targetNode = $this->findNodeById($scope, $targetId);
            if ($targetNode === null) {
                // Category outside platform scope — still allow if non-digital in full tree.
                $targetNode = $this->findNodeById($this->flattenCategoryNodes($tree), $targetId);
                if ($targetNode === null || isset($digitalLookup[$targetId])) {
                    return [];
                }
            }

            return array_values(array_unique(array_map(
                fn (array $node) => (int) $node['id'],
                $this->flattenCategoryNodes([$targetNode])
            )));
        }

        return array_values(array_unique(array_map(
            fn (array $node) => (int) $node['id'],
            $scope
        )));
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, mixed>|null
     */
    private function findNodeById(array $nodes, int $id): ?array
    {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            if ((int) ($node['id'] ?? 0) === $id) {
                return $node;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $tree
     * @return list<array<string, mixed>>
     */
    private function findPlatformNodes(array $tree, string $platform): array
    {
        $matches = [];
        foreach ($tree as $node) {
            if (! is_array($node)) {
                continue;
            }
            if ($this->matchesPlatform($node, $platform)) {
                $matches[] = $node;
                continue;
            }
            $subs = $node['sub_categories'] ?? [];
            if (is_array($subs) && $subs !== []) {
                foreach ($this->findPlatformNodes($subs, $platform) as $child) {
                    $matches[] = $child;
                }
            }
        }

        return $matches;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private function flattenCategoryNodes(array $nodes): array
    {
        $out = [];
        foreach ($nodes as $node) {
            if (! is_array($node) || empty($node['id'])) {
                continue;
            }
            $out[] = $node;
            $subs = $node['sub_categories'] ?? [];
            if (is_array($subs) && $subs !== []) {
                foreach ($this->flattenCategoryNodes($subs) as $child) {
                    $out[] = $child;
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $tree
     * @return list<int>
     */
    private function collectDigitalCategoryIds(array $tree): array
    {
        $ids = [];
        foreach ($this->flattenCategoryNodes($tree) as $node) {
            if ($this->isDigitalCatalogCategory($node)) {
                $ids[] = (int) $node['id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $category
     */
    public function isDigitalCatalogCategory(array $category): bool
    {
        $hay = strtolower(trim(($category['slug'] ?? '').' '.($category['name'] ?? '')));
        if (preg_match('/(digital[\s_-]*games?|gift[\s_-]*cards?|giftcards?)/u', $hay)) {
            return true;
        }
        $name = trim((string) ($category['name'] ?? ''));

        return str_contains($name, 'ألعاب رقمية') || str_contains($name, 'بطاقات الهدايا');
    }

    /**
     * @param  array<string, mixed>  $category
     */
    private function matchesPlatform(array $category, string $platform): bool
    {
        $hay = strtolower(trim(($category['slug'] ?? '').' '.($category['name'] ?? '')));
        if ($platform === 'ps5') {
            return (bool) preg_match('/ps\s*5|playstation\s*5|بلايستيشن\s*5|بلاي\s*ستيشن\s*5/u', $hay);
        }

        return (bool) preg_match('/ps\s*4|playstation\s*4|بلايستيشن\s*4|بلاي\s*ستيشن\s*4/u', $hay);
    }
}
