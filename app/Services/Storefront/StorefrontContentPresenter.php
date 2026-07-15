<?php

namespace App\Services\Storefront;

use App\Brands;
use App\Category;
use App\Product;
use App\ProductTranslation;
use App\Support\StorefrontLocale;
use App\Variation;
use Illuminate\Support\Str;

/**
 * Applies storefront locale overlays to POS catalog entities.
 */
class StorefrontContentPresenter
{
    public function productTranslation(Product $product, string $locale): ?ProductTranslation
    {
        if (StorefrontLocale::isDefault($locale)) {
            return null;
        }

        if ($product->relationLoaded('storefrontTranslations')) {
            return $product->storefrontTranslations->firstWhere('locale', $locale);
        }

        return $product->storefrontTranslations()->where('locale', $locale)->first();
    }

    public function applyProduct(Product $product, string $locale): Product
    {
        if (StorefrontLocale::isDefault($locale)) {
            return $product;
        }

        $translation = $this->productTranslation($product, $locale);
        if (empty($translation)) {
            return $product;
        }

        $product->name = $translation->name;
        if ($translation->product_description !== null) {
            $product->product_description = $translation->product_description;
        }
        if (! empty($translation->slug)) {
            $product->slug = $translation->slug;
        }

        return $product;
    }

    public function variationName(Variation $variation, string $locale): string
    {
        if (StorefrontLocale::isDefault($locale)) {
            return $variation->name;
        }

        if ($variation->relationLoaded('storefrontTranslations')) {
            $row = $variation->storefrontTranslations->firstWhere('locale', $locale);
        } else {
            $row = $variation->storefrontTranslations()->where('locale', $locale)->first();
        }

        return $row?->name ?? $variation->name;
    }

    public function brandName(?Brands $brand, string $locale): ?string
    {
        if (empty($brand)) {
            return null;
        }

        if (StorefrontLocale::isDefault($locale)) {
            return $brand->name;
        }

        if ($brand->relationLoaded('storefrontTranslations')) {
            $row = $brand->storefrontTranslations->firstWhere('locale', $locale);
        } else {
            $row = $brand->storefrontTranslations()->where('locale', $locale)->first();
        }

        return $row?->name ?? $brand->name;
    }

    /**
     * @return array{id:int,name:string,slug:?string}|array{}
     */
    public function brandFields(Brands $brand, string $locale): array
    {
        $name = $brand->name;
        $slug = $brand->slug;

        if (! StorefrontLocale::isDefault($locale)) {
            $row = $brand->relationLoaded('storefrontTranslations')
                ? $brand->storefrontTranslations->firstWhere('locale', $locale)
                : $brand->storefrontTranslations()->where('locale', $locale)->first();

            if (empty($row)) {
                return [];
            }

            $name = $row->name;
        }

        if (empty($slug)) {
            return [];
        }

        return [
            'id' => (int) $brand->id,
            'name' => $name,
            'slug' => $slug,
            'image_url' => $brand->image_url,
        ];
    }

    public function categoryFields(Category $category, string $locale): array
    {
        $name = $category->name;
        $slug = $category->slug;

        if (! StorefrontLocale::isDefault($locale)) {
            $row = $category->relationLoaded('storefrontTranslations')
                ? $category->storefrontTranslations->firstWhere('locale', $locale)
                : $category->storefrontTranslations()->where('locale', $locale)->first();

            if (empty($row)) {
                return [];
            }

            $name = $row->name;
            if (! empty($row->slug)) {
                $slug = $row->slug;
            }
        }

        return [
            'id' => (int) $category->id,
            'name' => $name,
            'slug' => $slug,
            'parent_id' => (int) $category->parent_id,
            'image_url' => $category->image_url,
        ];
    }

    /**
     * @param  array<string, mixed>|string|null  $value
     */
    public function localizedSetting(array|string|null $value, string $locale, string $fallback = ''): string
    {
        if (is_array($value)) {
            if (! empty($value[$locale])) {
                return (string) $value[$locale];
            }
            if (! empty($value[StorefrontLocale::DEFAULT])) {
                return (string) $value[StorefrontLocale::DEFAULT];
            }

            return $fallback;
        }

        return (string) ($value ?? $fallback);
    }

    public function slugFromName(string $name, int $businessId, string $table, ?int $excludeId = null): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        $i = 1;

        while ($this->slugExists($table, $slug, $businessId, $excludeId)) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function slugExists(string $table, string $slug, int $businessId, ?int $excludeId): bool
    {
        if ($table === 'products') {
            $primary = Product::where('business_id', $businessId)->where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists();
            if ($primary) {
                return true;
            }

            return ProductTranslation::where('slug', $slug)
                ->whereHas('product', fn ($q) => $q->where('business_id', $businessId))
                ->when($excludeId, fn ($q) => $q->where('product_id', '!=', $excludeId))
                ->exists();
        }

        if ($table === 'categories') {
            $primary = Category::where('business_id', $businessId)->where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists();
            if ($primary) {
                return true;
            }

            return \App\CategoryTranslation::where('slug', $slug)
                ->whereHas('category', fn ($q) => $q->where('business_id', $businessId))
                ->when($excludeId, fn ($q) => $q->where('category_id', '!=', $excludeId))
                ->exists();
        }

        return false;
    }
}
