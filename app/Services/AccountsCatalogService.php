<?php

namespace App\Services;

use App\Business;
use App\BusinessLocation;
use App\Category;
use App\Product;
use App\Support\StorefrontLocale;
use App\Unit;
use App\Utils\ProductUtil;
use App\Variation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Upserts one hidden POS product per Accounts offer (game+platform+offer or card category).
 *
 * Products carry the invoice name only: stock and credentials stay in Accounts, so they are
 * not stock-tracked and are flagged not_for_selling to keep them out of POS search and the shop.
 */
class AccountsCatalogService
{
    public const GAME_CATEGORY_SLUG = 'accounts-digital-games';

    public const CARD_CATEGORY_SLUG = 'accounts-gift-cards';

    public const SKU_PREFIXES = ['ACCOUNTS-GAME-', 'ACCOUNTS-CARD-'];

    private const NAME_MAX = 191;

    public function __construct(private ProductUtil $productUtil)
    {
    }

    /**
     * @param  array<int, array{sku:string,name:string,price:float|int|string,active?:bool}>  $items
     * @param  bool  $onlyExisting  Update matching SKUs only (used to hide a deleted game's offers).
     * @return list<array{sku:string,product_id:int,variation_id:int,active:bool}>
     */
    public function upsert(int $businessId, string $kind, array $items, ?string $code = null, bool $onlyExisting = false): array
    {
        $business = Business::with('owner')->findOrFail($businessId);
        $ownerId = (int) ($business->owner->id ?? 0);
        if ($ownerId <= 0) {
            throw new RuntimeException('Business owner is required to create catalog products.');
        }

        $unitId = $this->resolveUnitId($business);
        $categoryId = $this->resolveCategoryId($businessId, $ownerId, $kind);
        $locationIds = BusinessLocation::where('business_id', $businessId)->pluck('id')->all();

        $results = [];
        foreach ($items as $item) {
            $sku = (string) $item['sku'];
            if ($onlyExisting && ! Product::where('business_id', $businessId)->where('sku', $sku)->exists()) {
                continue;
            }
            // Serialise per SKU across workers; the row lock below covers the same DB connection.
            $results[] = Cache::lock('accounts-catalog:'.$businessId.':'.$sku, 30)->block(10, function () use (
                $businessId, $item, $sku, $ownerId, $unitId, $categoryId, $locationIds, $code
            ) {
                return DB::transaction(fn () => $this->upsertOne(
                    $businessId, $item, $sku, $ownerId, $unitId, $categoryId, $locationIds, $code
                ));
            });
        }

        return $results;
    }

    /**
     * @param  array{sku:string,name:string,price:float|int|string,active?:bool}  $item
     * @param  list<int>  $locationIds
     * @return array{sku:string,product_id:int,variation_id:int,active:bool}
     */
    private function upsertOne(
        int $businessId,
        array $item,
        string $sku,
        int $ownerId,
        int $unitId,
        int $categoryId,
        array $locationIds,
        ?string $code
    ): array {
        $price = max(0, round((float) $item['price'], 4));
        $active = ! array_key_exists('active', $item) || (bool) $item['active'];

        $product = Product::where('business_id', $businessId)
            ->where('sku', $sku)
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        $name = $this->uniqueName($businessId, (string) $item['name'], $sku, $code);

        if (! $product) {
            $product = Product::create(array_merge([
                'name' => $name,
                'business_id' => $businessId,
                'type' => 'single',
                'unit_id' => $unitId,
                'category_id' => $categoryId,
                'tax' => null,
                'tax_type' => 'exclusive',
                'enable_stock' => 0,
                'alert_quantity' => 0,
                'sku' => $sku,
                'barcode_type' => 'C128',
                'created_by' => $ownerId,
                'is_inactive' => $active ? 0 : 1,
                'not_for_selling' => 1,
            ], $this->wooSyncColumn()));

            $this->productUtil->createSingleProductVariation($product, $sku, $price, $price, 0, $price, $price);
        } else {
            $product->fill(array_merge([
                'name' => $name,
                'category_id' => $categoryId,
                'enable_stock' => 0,
                'not_for_selling' => 1,
                'is_inactive' => $active ? 0 : 1,
            ], $this->wooSyncColumn()));
            $product->save();
        }

        $variation = Variation::where('product_id', $product->id)->whereNull('deleted_at')->orderBy('id')->first();
        if (! $variation) {
            $this->productUtil->createSingleProductVariation($product, $sku, $price, $price, 0, $price, $price);
            $variation = Variation::where('product_id', $product->id)->whereNull('deleted_at')->orderBy('id')->firstOrFail();
        } else {
            $variation->update([
                'default_purchase_price' => $price,
                'dpp_inc_tax' => $price,
                'profit_percent' => 0,
                'default_sell_price' => $price,
                'sell_price_inc_tax' => $price,
            ]);
        }

        if ($locationIds !== []) {
            $product->product_locations()->syncWithoutDetaching($locationIds);
        }

        return [
            'sku' => $sku,
            'product_id' => (int) $product->id,
            'variation_id' => (int) $variation->id,
            'active' => $active,
        ];
    }

    /**
     * Same title on two games gives the same offer name; append the game code only on a clash.
     */
    private function uniqueName(int $businessId, string $name, string $sku, ?string $code): string
    {
        $name = mb_substr(trim($name), 0, self::NAME_MAX);
        $code = trim((string) $code);
        if ($code === '') {
            return $name;
        }

        $clash = Product::where('business_id', $businessId)
            ->where('name', $name)
            ->where('sku', '!=', $sku)
            ->exists();
        if (! $clash) {
            return $name;
        }

        $suffix = ' ('.$code.')';

        return mb_substr($name, 0, self::NAME_MAX - mb_strlen($suffix)).$suffix;
    }

    private function resolveUnitId(Business $business): int
    {
        $unitId = (int) ($business->default_unit ?? 0);
        if ($unitId > 0 && Unit::where('business_id', $business->id)->whereKey($unitId)->exists()) {
            return $unitId;
        }

        $fallback = (int) Unit::where('business_id', $business->id)->orderBy('id')->value('id');
        if ($fallback <= 0) {
            throw new RuntimeException('Business has no unit to assign to catalog products.');
        }

        return $fallback;
    }

    private function resolveCategoryId(int $businessId, int $ownerId, string $kind): int
    {
        $slug = $kind === 'card' ? self::CARD_CATEGORY_SLUG : self::GAME_CATEGORY_SLUG;
        $existing = Category::where('business_id', $businessId)
            ->where('category_type', 'product')
            ->where('slug', $slug)
            ->value('id');
        if ($existing) {
            return (int) $existing;
        }

        $category = Category::create([
            'name' => $kind === 'card' ? 'Gift Cards' : 'Digital Games',
            'business_id' => $businessId,
            'parent_id' => 0,
            'created_by' => $ownerId,
            'category_type' => 'product',
            'slug' => $slug,
        ]);

        foreach (StorefrontLocale::SUPPORTED as $locale) {
            Cache::forget('storefront.categories.'.$businessId.'.'.$locale);
        }

        return (int) $category->id;
    }

    /**
     * @return array<string, int>
     */
    private function wooSyncColumn(): array
    {
        static $hasColumn = null;
        $hasColumn ??= Schema::hasColumn('products', 'woocommerce_disable_sync');

        return $hasColumn ? ['woocommerce_disable_sync' => 1] : [];
    }

    public static function isAccountsSku(?string $sku): bool
    {
        $sku = (string) $sku;
        foreach (self::SKU_PREFIXES as $prefix) {
            if (str_starts_with($sku, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
