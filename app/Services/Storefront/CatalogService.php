<?php

namespace App\Services\Storefront;

use App\Category;
use App\CategoryTranslation;
use App\Product;
use App\ProductTranslation;
use App\Support\StorefrontLocale;
use App\Variation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Catalog queries for the public storefront API.
 */
class CatalogService
{
    public function __construct(
        private StorefrontSettingService $storefrontSettings,
        private StorefrontPricing $storefrontPricing,
        private StorefrontContentPresenter $presenter
    ) {
    }

    public function hasSellingLocations(int $businessId): bool
    {
        return ! empty($this->storefrontSettings->getSellingLocationIds($businessId));
    }

    public function getCategories(int $businessId, string $locale = StorefrontLocale::DEFAULT): array
    {
        if (! $this->hasSellingLocations($businessId)) {
            return [];
        }

        $tree = Category::catAndSubCategories($businessId);
        if (StorefrontLocale::isDefault($locale)) {
            return $tree;
        }

        $translatedIds = CategoryTranslation::query()
            ->where('locale', $locale)
            ->whereIn('category_id', $this->categoryIdsFromTree($tree))
            ->pluck('category_id')
            ->flip();

        return $this->filterCategoryTree($tree, $translatedIds, $locale);
    }

    /**
     * @return array{id:int,name:string,slug:?string,parent_id:int}|null
     */
    public function findCategoryBySlug(int $businessId, string $slug, string $locale = StorefrontLocale::DEFAULT): ?array
    {
        if (! $this->hasSellingLocations($businessId)) {
            return null;
        }

        if (! StorefrontLocale::isDefault($locale)) {
            $translation = CategoryTranslation::query()
                ->where('locale', $locale)
                ->where('slug', $slug)
                ->whereHas('category', function ($q) use ($businessId) {
                    $q->where('business_id', $businessId)->where('category_type', 'product');
                })
                ->with('category')
                ->first();

            if (empty($translation?->category)) {
                return null;
            }

            return $this->presenter->categoryFields($translation->category, $locale);
        }

        $category = Category::where('business_id', $businessId)
            ->where('category_type', 'product')
            ->where('slug', $slug)
            ->first();

        if (empty($category)) {
            return null;
        }

        return $this->presenter->categoryFields($category, $locale);
    }

    public function listProducts(
        int $businessId,
        array $filters = [],
        int $perPage = 20,
        string $locale = StorefrontLocale::DEFAULT
    ): LengthAwarePaginator {
        $locationIds = $this->storefrontSettings->getSellingLocationIds($businessId);
        if (empty($locationIds)) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        if (empty($filters['category_id']) && ! empty($filters['category_slug'])) {
            $category = $this->findCategoryBySlug($businessId, (string) $filters['category_slug'], $locale);
            if (empty($category)) {
                return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
            }
            $filters['category_id'] = $category['id'];
        }

        $query = $this->baseProductQuery($businessId, $locationIds);
        $this->applyLocaleProductFilter($query, $locale);

        if (! empty($filters['category_id'])) {
            $query->where(function (Builder $q) use ($filters) {
                $q->where('products.category_id', $filters['category_id'])
                    ->orWhere('products.sub_category_id', $filters['category_id']);
            });
        }

        if (! empty($filters['brand_id'])) {
            $query->where('products.brand_id', $filters['brand_id']);
        }

        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(function (Builder $q) use ($term, $locale) {
                $q->where('products.name', 'like', $term)
                    ->orWhere('products.sku', 'like', $term)
                    ->orWhere('variations.sub_sku', 'like', $term);
                if (! StorefrontLocale::isDefault($locale)) {
                    $q->orWhereHas('storefrontTranslations', function (Builder $tq) use ($term, $locale) {
                        $tq->where('locale', $locale)->where('name', 'like', $term);
                    });
                }
            });
        }

        if (! empty($filters['in_stock_only'])) {
            $query->where(function (Builder $q) {
                $q->where('products.enable_stock', 0)
                    ->orWhere('vld.qty_available', '>', 0);
            });
        }

        $sort = $filters['sort'] ?? 'default';
        match ($sort) {
            'name' => $this->applyNameSort($query, $locale),
            'price_asc' => $query->orderBy('variations.sell_price_inc_tax', 'asc'),
            'price_desc' => $query->orderBy('variations.sell_price_inc_tax', 'desc'),
            'newest' => $query->orderBy('products.created_at', 'desc'),
            // Catalog / POS order — no A–Z or price sort.
            default => $query->orderBy('products.id', 'asc'),
        };

        $query->select('products.*')->groupBy('products.id');
        $query->with([
            'storefrontTranslations' => fn ($q) => $q->where('locale', $locale),
            'brand.storefrontTranslations' => fn ($q) => $q->where('locale', $locale),
        ]);

        $paginator = $query->paginate($perPage);
        $paginator->getCollection()->transform(
            fn (Product $p) => $this->formatProductSummary($p, $locationIds, $locale)
        );

        return $paginator;
    }

    public function findProduct(
        int $businessId,
        string $idOrSlug,
        ?array $locationIds = null,
        string $locale = StorefrontLocale::DEFAULT
    ): ?array {
        $locationIds = $locationIds ?? $this->storefrontSettings->getSellingLocationIds($businessId);
        if (empty($locationIds)) {
            return null;
        }

        $product = $this->resolveProduct($businessId, $idOrSlug, $locationIds, $locale);
        if (empty($product)) {
            return null;
        }

        if (! StorefrontLocale::isDefault($locale) && empty($this->presenter->productTranslation($product, $locale))) {
            return null;
        }

        return $this->formatProductDetail($product, $locationIds, $locale);
    }

    public function search(int $businessId, string $q, int $limit = 8, string $locale = StorefrontLocale::DEFAULT): array
    {
        $locationIds = $this->storefrontSettings->getSellingLocationIds($businessId);
        if (empty($locationIds) || trim($q) === '') {
            return [];
        }

        $query = $this->baseProductQuery($businessId, $locationIds);
        $this->applyLocaleProductFilter($query, $locale);

        $term = '%'.$q.'%';
        $query->where(function (Builder $qb) use ($term, $locale) {
            $qb->where('products.name', 'like', $term)
                ->orWhere('products.sku', 'like', $term)
                ->orWhere('variations.sub_sku', 'like', $term);
            if (! StorefrontLocale::isDefault($locale)) {
                $qb->orWhereHas('storefrontTranslations', function (Builder $tq) use ($term, $locale) {
                    $tq->where('locale', $locale)->where('name', 'like', $term);
                });
            }
        });

        return $query->select('products.*')
            ->groupBy('products.id')
            ->with(['storefrontTranslations' => fn ($q) => $q->where('locale', $locale)])
            ->limit($limit)
            ->get()
            ->map(fn (Product $p) => $this->formatProductSummary($p, $locationIds, $locale))
            ->values()
            ->all();
    }

    /**
     * @param  int[]  $productIds
     * @return array<int, array<string, mixed>>
     */
    public function productSummariesByIds(
        int $businessId,
        array $productIds,
        string $locale = StorefrontLocale::DEFAULT
    ): array {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($productIds === [] || ! $this->hasSellingLocations($businessId)) {
            return [];
        }

        $locationIds = $this->storefrontSettings->getSellingLocationIds($businessId);
        $query = $this->baseProductQuery($businessId, $locationIds)
            ->whereIn('products.id', $productIds);
        $this->applyLocaleProductFilter($query, $locale);

        $products = $query->select('products.*')
            ->groupBy('products.id')
            ->with(['storefrontTranslations' => fn ($q) => $q->where('locale', $locale)])
            ->get()
            ->keyBy('id');

        $items = [];
        foreach ($productIds as $productId) {
            $product = $products->get($productId);
            if ($product) {
                $items[] = $this->formatProductSummary($product, $locationIds, $locale);
            }
        }

        return $items;
    }

    public function isProductWishlistable(int $businessId, int $productId, string $locale = StorefrontLocale::DEFAULT): bool
    {
        return $this->productSummariesByIds($businessId, [$productId], $locale) !== [];
    }

    public function generateSlug(string $name, int $businessId, ?int $excludeId = null): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $i = 1;

        while (Product::where('business_id', $businessId)
            ->where('slug', $slug)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function resolveProduct(int $businessId, string $idOrSlug, array $locationIds, string $locale): ?Product
    {
        $baseQuery = Product::where('business_id', $businessId)
            ->active()
            ->where('not_for_selling', 0)
            ->where('type', '!=', 'modifier')
            ->whereHas('product_locations', fn ($q) => $q->whereIn('product_locations.location_id', $locationIds));

        if (is_numeric($idOrSlug)) {
            $product = (clone $baseQuery)->where('id', (int) $idOrSlug)->first();
        } elseif (! StorefrontLocale::isDefault($locale)) {
            $translation = ProductTranslation::where('locale', $locale)
                ->where('slug', $idOrSlug)
                ->whereHas('product', function ($q) use ($businessId) {
                    $q->where('business_id', $businessId)->active()->where('not_for_selling', 0);
                })
                ->first();

            $product = $translation
                ? (clone $baseQuery)->where('id', $translation->product_id)->first()
                : null;
        } else {
            $product = (clone $baseQuery)->where('slug', $idOrSlug)->first();
        }

        if (empty($product)) {
            return null;
        }

        $product->load([
            'brand.storefrontTranslations' => fn ($q) => $q->where('locale', $locale),
            'category.storefrontTranslations' => fn ($q) => $q->where('locale', $locale),
            'sub_category.storefrontTranslations' => fn ($q) => $q->where('locale', $locale),
            'media',
            'storefrontTranslations' => fn ($q) => $q->where('locale', $locale),
            'product_variations.variations' => fn ($q) => $q->whereNull('deleted_at'),
            'product_variations.variations.storefrontTranslations' => fn ($q) => $q->where('locale', $locale),
            'product_variations.variations.media',
            'product_variations.variations.variation_location_details' => fn ($q) => $q->whereIn('variation_location_details.location_id', $locationIds),
        ]);

        return $product;
    }

    private function applyLocaleProductFilter(Builder $query, string $locale): void
    {
        if (! StorefrontLocale::isDefault($locale)) {
            $query->whereHas('storefrontTranslations', fn (Builder $q) => $q->where('locale', $locale));
        }
    }

    /**
     * Sort by the name shown to the shopper: POS name for default locale,
     * translation name for AR (and other overlays).
     */
    private function applyNameSort(Builder $query, string $locale): void
    {
        if (StorefrontLocale::isDefault($locale)) {
            $query->orderBy('products.name', 'asc');

            return;
        }

        $query->orderByRaw(
            '(SELECT pt.name FROM product_translations pt WHERE pt.product_id = products.id AND pt.locale = ? LIMIT 1) ASC',
            [$locale]
        );
    }

    private function baseProductQuery(int $businessId, array $locationIds): Builder
    {
        return Product::query()
            ->where('products.business_id', $businessId)
            ->active()
            ->where('products.not_for_selling', 0)
            ->where('products.type', '!=', 'modifier')
            ->join('variations', 'products.id', '=', 'variations.product_id')
            ->whereNull('variations.deleted_at')
            ->join('product_locations as pl', function ($join) use ($locationIds) {
                $join->on('pl.product_id', '=', 'products.id')
                    ->whereIn('pl.location_id', $locationIds);
            })
            ->leftJoin('variation_location_details as vld', function ($join) use ($locationIds) {
                $join->on('vld.variation_id', '=', 'variations.id')
                    ->whereIn('vld.location_id', $locationIds);
            });
    }

    private function formatProductSummary(Product $product, array $locationIds, string $locale): array
    {
        $product = $this->presenter->applyProduct($product, $locale);

        $variations = $product->variations()->whereNull('deleted_at')->get();
        $defaultVariation = $variations->first();
        $hasOptions = $product->type === 'variable';
        $inStock = $this->isProductInStock($product, $locationIds);

        $pricingRows = $variations->map(fn (Variation $v) => $this->storefrontPricing->resolve($v));
        $minPrice = $pricingRows->min('price') ?? 0;
        $onSaleRow = $pricingRows->first(fn (array $row) => $row['on_sale'])
            ?? ($defaultVariation
                ? $this->storefrontPricing->resolve($defaultVariation)
                : ['compare_at_price' => null, 'sale_percent' => 0, 'on_sale' => false]);

        $variationName = null;
        if ($defaultVariation && $defaultVariation->name !== 'DUMMY') {
            $variationName = $this->presenter->variationName($defaultVariation, $locale);
        }

        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'sku' => $product->sku,
            'type' => $product->type,
            'image_url' => $product->image_url,
            'variation_id' => $defaultVariation?->id,
            'variation_name' => $variationName,
            'has_options' => $hasOptions,
            'price' => (float) $minPrice,
            'compare_at_price' => $onSaleRow['compare_at_price'],
            'on_sale' => $pricingRows->contains(fn (array $row) => $row['on_sale']),
            'sale_percent' => (int) $onSaleRow['sale_percent'],
            'in_stock' => $inStock,
        ];
    }

    private function formatProductDetail(Product $product, array $locationIds, string $locale): array
    {
        $product = $this->presenter->applyProduct($product, $locale);

        $variations = [];
        foreach ($product->product_variations as $pv) {
            foreach ($pv->variations as $variation) {
                $variations[] = $this->formatVariation($variation, $product, $locationIds, $locale);
            }
        }

        $images = collect([$product->image_url])
            ->merge($product->media->pluck('display_url'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $category = $product->category;
        $categoryPayload = null;
        if ($category) {
            $fields = $this->presenter->categoryFields($category, $locale);
            if (! empty($fields)) {
                $categoryPayload = [
                    'id' => $fields['id'],
                    'name' => $fields['name'],
                    'slug' => $fields['slug'],
                ];
            }
        }

        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'sku' => $product->sku,
            'type' => $product->type,
            'description' => $product->product_description,
            'brand' => $product->brand ? [
                'id' => $product->brand->id,
                'name' => $this->presenter->brandName($product->brand, $locale),
            ] : null,
            'category' => $categoryPayload,
            'images' => $images,
            'enable_stock' => (bool) $product->enable_stock,
            'variations' => $variations,
        ];
    }

    private function formatVariation(Variation $variation, Product $product, array $locationIds, string $locale): array
    {
        $qty = 0;
        if ($product->enable_stock) {
            $qty = $variation->variation_location_details
                ->whereIn('location_id', $locationIds)
                ->sum('qty_available');
        }

        $images = $variation->media->pluck('display_url')->filter()->values()->all();
        $pricing = $this->storefrontPricing->resolve($variation);

        return [
            'id' => $variation->id,
            'name' => $this->presenter->variationName($variation, $locale),
            'sub_sku' => $variation->sub_sku,
            'price' => $pricing['price'],
            'compare_at_price' => $pricing['compare_at_price'],
            'on_sale' => $pricing['on_sale'],
            'sale_percent' => $pricing['sale_percent'],
            'in_stock' => ! $product->enable_stock || $qty > 0,
            'qty_available' => (float) $qty,
            'images' => $images,
        ];
    }

    private function isProductInStock(Product $product, array $locationIds): bool
    {
        if (! $product->enable_stock) {
            return true;
        }

        return Variation::where('product_id', $product->id)
            ->whereNull('deleted_at')
            ->whereHas('variation_location_details', fn ($q) => $q->whereIn('location_id', $locationIds)->where('qty_available', '>', 0))
            ->exists();
    }

    /**
     * @param  array<int, array<string, mixed>>  $tree
     * @return list<int>
     */
    private function categoryIdsFromTree(array $tree): array
    {
        $ids = [];
        foreach ($tree as $row) {
            $ids[] = (int) $row['id'];
            foreach ($row['sub_categories'] ?? [] as $sub) {
                $ids[] = (int) $sub['id'];
            }
        }

        return $ids;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tree
     * @param  \Illuminate\Support\Collection<int, int>  $translatedIds
     * @return array<int, array<string, mixed>>
     */
    private function filterCategoryTree(array $tree, $translatedIds, string $locale): array
    {
        $out = [];
        foreach ($tree as $parent) {
            if (! $translatedIds->has((int) $parent['id'])) {
                continue;
            }

            $parentTrans = CategoryTranslation::where('category_id', $parent['id'])->where('locale', $locale)->first();
            if ($parentTrans) {
                $parent['name'] = $parentTrans->name;
                if (! empty($parentTrans->slug)) {
                    $parent['slug'] = $parentTrans->slug;
                }
            }

            $subs = [];
            foreach ($parent['sub_categories'] ?? [] as $sub) {
                if (! $translatedIds->has((int) $sub['id'])) {
                    continue;
                }
                $subTrans = CategoryTranslation::where('category_id', $sub['id'])->where('locale', $locale)->first();
                if ($subTrans) {
                    $sub['name'] = $subTrans->name;
                    if (! empty($subTrans->slug)) {
                        $sub['slug'] = $subTrans->slug;
                    }
                }
                $subs[] = $sub;
            }

            $parent['sub_categories'] = $subs;
            $out[] = $parent;
        }

        return $out;
    }
}
