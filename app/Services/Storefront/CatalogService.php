<?php

namespace App\Services\Storefront;

use App\Category;
use App\Product;
use App\Variation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Catalog queries for the public storefront API.
 */
class CatalogService
{
    public function __construct(
        private StorefrontSettingService $storefrontSettings
    ) {
    }

    public function hasSellingLocations(int $businessId): bool
    {
        return ! empty($this->storefrontSettings->getSellingLocationIds($businessId));
    }

    public function getCategories(int $businessId): array
    {
        if (! $this->hasSellingLocations($businessId)) {
            return [];
        }

        return Category::catAndSubCategories($businessId);
    }

    public function listProducts(int $businessId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $locationIds = $this->storefrontSettings->getSellingLocationIds($businessId);
        if (empty($locationIds)) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        $query = $this->baseProductQuery($businessId, $locationIds);

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
            $query->where(function (Builder $q) use ($term) {
                $q->where('products.name', 'like', $term)
                    ->orWhere('products.sku', 'like', $term)
                    ->orWhere('variations.sub_sku', 'like', $term);
            });
        }

        if (! empty($filters['in_stock_only'])) {
            $query->where(function (Builder $q) {
                $q->where('products.enable_stock', 0)
                    ->orWhere('vld.qty_available', '>', 0);
            });
        }

        $sort = $filters['sort'] ?? 'name';
        match ($sort) {
            'price_asc' => $query->orderBy('variations.sell_price_inc_tax', 'asc'),
            'price_desc' => $query->orderBy('variations.sell_price_inc_tax', 'desc'),
            'newest' => $query->orderBy('products.created_at', 'desc'),
            default => $query->orderBy('products.name', 'asc'),
        };

        $query->select('products.*')
            ->groupBy('products.id');

        $paginator = $query->paginate($perPage);
        $paginator->getCollection()->transform(fn (Product $p) => $this->formatProductSummary($p, $locationIds));

        return $paginator;
    }

    public function findProduct(int $businessId, string $idOrSlug, array $locationIds = null): ?array
    {
        $locationIds = $locationIds ?? $this->storefrontSettings->getSellingLocationIds($businessId);
        if (empty($locationIds)) {
            return null;
        }

        $query = Product::where('business_id', $businessId)
            ->active()
            ->where('not_for_selling', 0)
            ->where('type', '!=', 'modifier')
            ->whereHas('product_locations', fn ($q) => $q->whereIn('location_id', $locationIds));

        if (is_numeric($idOrSlug)) {
            $query->where('id', (int) $idOrSlug);
        } else {
            $query->where('slug', $idOrSlug);
        }

        $product = $query->with([
            'brand',
            'category',
            'sub_category',
            'media',
            'product_variations.variations' => fn ($q) => $q->whereNull('deleted_at'),
            'product_variations.variations.media',
            'product_variations.variations.variation_location_details' => fn ($q) => $q->whereIn('location_id', $locationIds),
        ])->first();

        if (empty($product)) {
            return null;
        }

        return $this->formatProductDetail($product, $locationIds);
    }

    public function search(int $businessId, string $q, int $limit = 8): array
    {
        $locationIds = $this->storefrontSettings->getSellingLocationIds($businessId);
        if (empty($locationIds) || trim($q) === '') {
            return [];
        }

        $query = $this->baseProductQuery($businessId, $locationIds);
        $term = '%'.$q.'%';
        $query->where(function (Builder $qb) use ($term) {
            $qb->where('products.name', 'like', $term)
                ->orWhere('products.sku', 'like', $term)
                ->orWhere('variations.sub_sku', 'like', $term);
        });

        return $query->select('products.*')
            ->groupBy('products.id')
            ->limit($limit)
            ->get()
            ->map(fn (Product $p) => $this->formatProductSummary($p, $locationIds))
            ->values()
            ->all();
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

    private function formatProductSummary(Product $product, array $locationIds): array
    {
        $variation = $product->variations()->whereNull('deleted_at')->first();
        $price = $variation?->sell_price_inc_tax ?? 0;
        $inStock = $this->isProductInStock($product, $locationIds);

        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'sku' => $product->sku,
            'type' => $product->type,
            'image_url' => $product->image_url,
            'price' => (float) $price,
            'in_stock' => $inStock,
        ];
    }

    private function formatProductDetail(Product $product, array $locationIds): array
    {
        $variations = [];
        foreach ($product->product_variations as $pv) {
            foreach ($pv->variations as $variation) {
                $variations[] = $this->formatVariation($variation, $product, $locationIds);
            }
        }

        $images = collect([$product->image_url])
            ->merge($product->media->pluck('display_url'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'sku' => $product->sku,
            'type' => $product->type,
            'description' => $product->product_description,
            'brand' => $product->brand ? ['id' => $product->brand->id, 'name' => $product->brand->name] : null,
            'category' => $product->category ? ['id' => $product->category->id, 'name' => $product->category->name, 'slug' => $product->category->slug] : null,
            'images' => $images,
            'enable_stock' => (bool) $product->enable_stock,
            'variations' => $variations,
        ];
    }

    private function formatVariation(Variation $variation, Product $product, array $locationIds): array
    {
        $qty = 0;
        if ($product->enable_stock) {
            $qty = $variation->variation_location_details
                ->whereIn('location_id', $locationIds)
                ->sum('qty_available');
        }

        $images = $variation->media->pluck('display_url')->filter()->values()->all();

        return [
            'id' => $variation->id,
            'name' => $variation->name,
            'sub_sku' => $variation->sub_sku,
            'price' => (float) $variation->sell_price_inc_tax,
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
}
