<?php

namespace App\Services\Storefront;

use App\StorefrontWishlistItem;
use App\Support\StorefrontLocale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WishlistService
{
    public function __construct(private CatalogService $catalog)
    {
    }

    public function list(int $businessId, int $contactId, string $locale = StorefrontLocale::DEFAULT): array
    {
        $productIds = StorefrontWishlistItem::query()
            ->where('business_id', $businessId)
            ->where('contact_id', $contactId)
            ->orderByDesc('created_at')
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $items = $this->catalog->productSummariesByIds($businessId, $productIds, $locale);

        return [
            'items' => $items,
            'count' => count($items),
        ];
    }

    public function add(int $businessId, int $contactId, int $productId, string $locale = StorefrontLocale::DEFAULT): array
    {
        $this->assertWishlistable($businessId, $productId, $locale);

        $exists = StorefrontWishlistItem::query()
            ->where('business_id', $businessId)
            ->where('contact_id', $contactId)
            ->where('product_id', $productId)
            ->exists();

        if (! $exists) {
            $this->assertHasCapacity($businessId, $contactId, 1);
        }

        StorefrontWishlistItem::firstOrCreate([
            'business_id' => $businessId,
            'contact_id' => $contactId,
            'product_id' => $productId,
        ]);

        return $this->list($businessId, $contactId, $locale);
    }

    public function remove(int $businessId, int $contactId, int $productId, string $locale = StorefrontLocale::DEFAULT): array
    {
        StorefrontWishlistItem::query()
            ->where('business_id', $businessId)
            ->where('contact_id', $contactId)
            ->where('product_id', $productId)
            ->delete();

        return $this->list($businessId, $contactId, $locale);
    }

    /**
     * @param  int[]  $productIds
     */
    public function merge(int $businessId, int $contactId, array $productIds, string $locale = StorefrontLocale::DEFAULT): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        $mergeMax = max(1, (int) config('storefront.wishlist_merge_max_ids', 100));
        $productIds = array_slice($productIds, 0, $mergeMax);

        $wishlistableIds = $this->catalog->filterWishlistableProductIds($businessId, $productIds, $locale);

        DB::transaction(function () use ($businessId, $contactId, $wishlistableIds) {
            $existingIds = StorefrontWishlistItem::query()
                ->where('business_id', $businessId)
                ->where('contact_id', $contactId)
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $existingSet = array_fill_keys($existingIds, true);

            $maxItems = max(1, (int) config('storefront.wishlist_max_items', 100));
            $remaining = max(0, $maxItems - count($existingIds));

            foreach ($wishlistableIds as $productId) {
                if ($remaining <= 0) {
                    break;
                }
                if (isset($existingSet[$productId])) {
                    continue;
                }

                StorefrontWishlistItem::firstOrCreate([
                    'business_id' => $businessId,
                    'contact_id' => $contactId,
                    'product_id' => $productId,
                ]);
                $existingSet[$productId] = true;
                $remaining--;
            }
        });

        return $this->list($businessId, $contactId, $locale);
    }

    private function assertWishlistable(int $businessId, int $productId, string $locale): void
    {
        if (! $this->catalog->isProductWishlistable($businessId, $productId, $locale)) {
            throw ValidationException::withMessages([
                'product_id' => ['Product is not available.'],
            ]);
        }
    }

    private function assertHasCapacity(int $businessId, int $contactId, int $incomingCount): void
    {
        $maxItems = max(1, (int) config('storefront.wishlist_max_items', 100));
        $current = StorefrontWishlistItem::query()
            ->where('business_id', $businessId)
            ->where('contact_id', $contactId)
            ->count();

        if ($current + $incomingCount > $maxItems) {
            throw ValidationException::withMessages([
                'product_id' => ['Wishlist is full. Remove items before adding more.'],
            ]);
        }
    }
}
