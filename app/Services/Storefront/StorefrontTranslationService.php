<?php

namespace App\Services\Storefront;

use App\Brands;
use App\Category;
use App\CategoryTranslation;
use App\Product;
use App\ProductTranslation;
use App\Support\StorefrontLocale;
use App\Variation;
use App\VariationTranslation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Storefront-only catalog translation CRUD (POS forms unchanged).
 */
class StorefrontTranslationService
{
    public function __construct(
        private StorefrontSettingService $storefrontSettings,
        private StorefrontContentPresenter $presenter
    ) {
    }

    public function listProducts(int $businessId, int $perPage = 25): LengthAwarePaginator
    {
        $locationIds = $this->storefrontSettings->getSellingLocationIds($businessId);

        return Product::query()
            ->where('business_id', $businessId)
            ->active()
            ->where('not_for_selling', 0)
            ->where('type', '!=', 'modifier')
            ->when(! empty($locationIds), fn ($q) => $q->whereHas(
                'product_locations',
                fn ($lq) => $lq->whereIn('product_locations.location_id', $locationIds)
            ))
            ->with(['storefrontTranslations' => fn ($q) => $q->where('locale', 'ar')])
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function getProductForEdit(int $businessId, int $productId): ?Product
    {
        return Product::where('business_id', $businessId)
            ->where('id', $productId)
            ->with([
                'storefrontTranslations',
                'product_variations.variations' => fn ($q) => $q->whereNull('deleted_at'),
                'product_variations.variations.storefrontTranslations',
            ])
            ->first();
    }

    public function saveProductTranslation(int $businessId, int $productId, array $data): void
    {
        $product = Product::where('business_id', $businessId)->where('id', $productId)->firstOrFail();

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            ProductTranslation::where('product_id', $product->id)->where('locale', 'ar')->delete();

            return;
        }

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = $this->presenter->slugFromName($name, $businessId, 'products', $product->id);
        }

        ProductTranslation::updateOrCreate(
            ['product_id' => $product->id, 'locale' => 'ar'],
            [
                'name' => $name,
                'product_description' => $data['product_description'] ?? null,
                'slug' => $slug,
            ]
        );

        foreach ($data['variations'] ?? [] as $variationId => $variationName) {
            $variationId = (int) $variationId;
            $variationName = trim((string) $variationName);
            $variation = Variation::where('id', $variationId)->where('product_id', $product->id)->first();
            if (empty($variation) || $variation->name === 'DUMMY') {
                continue;
            }

            if ($variationName === '') {
                VariationTranslation::where('variation_id', $variation->id)->where('locale', 'ar')->delete();

                continue;
            }

            VariationTranslation::updateOrCreate(
                ['variation_id' => $variation->id, 'locale' => 'ar'],
                ['name' => $variationName]
            );
        }
    }

    public function listCategories(int $businessId): array
    {
        $rows = Category::where('business_id', $businessId)
            ->where('category_type', 'product')
            ->orderBy('name')
            ->with(['storefrontTranslations' => fn ($q) => $q->where('locale', 'ar')])
            ->get();

        return $rows->map(function (Category $category) {
            $ar = $category->storefrontTranslations->first();

            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'parent_id' => (int) $category->parent_id,
                'has_ar' => ! empty($ar),
                'ar_name' => $ar?->name,
            ];
        })->all();
    }

    public function getCategoryForEdit(int $businessId, int $categoryId): ?Category
    {
        return Category::where('business_id', $businessId)
            ->where('id', $categoryId)
            ->with('storefrontTranslations')
            ->first();
    }

    public function saveCategoryTranslation(int $businessId, int $categoryId, array $data): void
    {
        $category = Category::where('business_id', $businessId)->where('id', $categoryId)->firstOrFail();
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            CategoryTranslation::where('category_id', $category->id)->where('locale', 'ar')->delete();

            return;
        }

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = $this->presenter->slugFromName($name, $businessId, 'categories', $category->id);
        }

        CategoryTranslation::updateOrCreate(
            ['category_id' => $category->id, 'locale' => 'ar'],
            ['name' => $name, 'slug' => $slug]
        );
    }

    public function listBrands(int $businessId): array
    {
        return Brands::where('business_id', $businessId)
            ->orderBy('name')
            ->with(['storefrontTranslations' => fn ($q) => $q->where('locale', 'ar')])
            ->get()
            ->map(function (Brands $brand) {
                $ar = $brand->storefrontTranslations->first();

                return [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'has_ar' => ! empty($ar),
                    'ar_name' => $ar?->name,
                ];
            })
            ->all();
    }

    public function getBrandForEdit(int $businessId, int $brandId): ?Brands
    {
        return Brands::where('business_id', $businessId)->where('id', $brandId)
            ->with('storefrontTranslations')
            ->first();
    }

    public function saveBrandTranslation(int $businessId, int $brandId, array $data): void
    {
        $brand = Brands::where('business_id', $businessId)->where('id', $brandId)->firstOrFail();
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            \App\BrandTranslation::where('brand_id', $brand->id)->where('locale', 'ar')->delete();

            return;
        }

        \App\BrandTranslation::updateOrCreate(
            ['brand_id' => $brand->id, 'locale' => 'ar'],
            ['name' => $name]
        );
    }
}
