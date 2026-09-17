<?php

namespace App\Services\Storefront;

use App\Category;
use App\Support\StorefrontLocale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Custom Bundle builder: physical catalog only (no digital games / gift cards).
 * Shared by Qwik and future Expo clients via GET /custom-bundle/*.
 */
class CustomBundleService
{
    public const PLATFORMS = ['ps5', 'ps4'];

    public const TABS = ['all', 'consoles', 'accessories', 'games'];

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
    public function meta(string $locale = StorefrontLocale::DEFAULT): array
    {
        $ar = $locale === 'ar';

        return [
            'enabled' => $this->isEnabled(),
            'min_items' => $this->minItems(),
            'max_items' => $this->maxItems(),
            'platforms' => [
                ['id' => 'ps5', 'label' => 'PS5'],
                ['id' => 'ps4', 'label' => 'PS4'],
            ],
            'tabs' => [
                ['id' => 'all', 'label' => $ar ? 'الكل' : 'All'],
                ['id' => 'consoles', 'label' => $ar ? 'أجهزة' : 'Consoles'],
                ['id' => 'accessories', 'label' => $ar ? 'إكسسوارات' : 'Accessories'],
                ['id' => 'games', 'label' => $ar ? 'ألعاب' : 'Games'],
            ],
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
        if (! in_array($tab, self::TABS, true)) {
            $tab = 'all';
        }

        $tree = Category::catAndSubCategories($businessId);
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
     * @param  list<array<string, mixed>>  $tree
     * @param  list<int>  $digitalIds
     * @return list<int>
     */
    private function resolveCategoryIds(array $tree, string $platform, string $tab, array $digitalIds): array
    {
        $digitalLookup = array_fill_keys($digitalIds, true);
        $platformNodes = $this->findPlatformNodes($tree, $platform);

        // Prefer categories under a matching PS4/PS5 parent; else all non-digital.
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

        $matched = array_values(array_filter(
            $scope,
            fn (array $node) => $this->matchesTab($node, $tab)
        ));

        // If tab heuristics miss (e.g. flat category tree), fall back to platform scope.
        if ($matched === []) {
            $matched = $scope;
        }

        return array_values(array_unique(array_map(
            fn (array $node) => (int) $node['id'],
            $matched
        )));
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

    /**
     * @param  array<string, mixed>  $category
     */
    private function matchesTab(array $category, string $tab): bool
    {
        if ($this->isDigitalCatalogCategory($category)) {
            return false;
        }
        $hay = strtolower(trim(($category['slug'] ?? '').' '.($category['name'] ?? '')));

        return match ($tab) {
            'consoles' => (bool) preg_match('/console|consoles|كونسول|أجهزة|اجهزة/u', $hay),
            'accessories' => (bool) preg_match('/accessor|controller|headset|إكسسوار|اكسسوار|ذراع/u', $hay),
            'games' => (bool) preg_match('/\bgames?\b|cd\s*games?|disc|ألعاب|العاب/u', $hay)
                && ! preg_match('/digital/u', $hay),
            default => true,
        };
    }
}
