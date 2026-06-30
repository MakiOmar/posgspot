<?php

namespace Database\Seeders;

use App\Business;
use App\BusinessLocation;
use App\Category;
use App\Product;
use App\PurchaseLine;
use App\Transaction;
use App\Unit;
use App\User;
use App\Utils\ProductUtil;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds 3 fully-formed variable products (variations + per-location opening
 * stock) for the configured storefront business.
 *
 * Mirrors the real product-create + opening-stock flow:
 *   - Product (type=variable, stock enabled) linked to all active locations
 *   - Variations via ProductUtil::createVariableProductVariations()
 *   - Opening stock as PurchaseLine + opening_stock Transaction + VLD rows
 *
 * Idempotent: skips products whose name already exists for the business.
 *
 * Run: php artisan db:seed --class=Database\\Seeders\\VariableProductsSeeder
 */
class VariableProductsSeeder extends Seeder
{
    public function __construct(private ProductUtil $productUtil)
    {
    }

    public function run(): void
    {
        $businessId = (int) config('storefront.business_id', 1);
        $business = Business::find($businessId) ?? Business::first();

        if (empty($business)) {
            $this->command?->error('VariableProductsSeeder: no business found; aborting.');

            return;
        }
        $businessId = $business->id;

        $userId = User::where('business_id', $businessId)->orderBy('id')->value('id') ?? 1;
        $unit = $this->resolveUnit($businessId, $userId);
        $categoryId = Category::where('business_id', $businessId)
            ->where('category_type', 'product')
            ->where('parent_id', 0)
            ->orderBy('id')
            ->value('id');

        $locations = BusinessLocation::where('business_id', $businessId)
            ->where('is_active', 1)
            ->orderBy('id')
            ->get();

        if ($locations->isEmpty()) {
            $this->command?->error('VariableProductsSeeder: no active business locations; aborting.');

            return;
        }
        $locationIds = $locations->pluck('id')->all();
        $skuPrefix = (string) ($business->sku_prefix ?? '');
        $transactionDate = Carbon::now()->toDateTimeString();

        foreach ($this->productDefinitions() as $definition) {
            $exists = Product::where('business_id', $businessId)
                ->where('name', $definition['name'])
                ->exists();

            if ($exists) {
                $this->command?->warn("VariableProductsSeeder: '{$definition['name']}' already exists; skipping.");

                continue;
            }

            DB::transaction(function () use (
                $definition,
                $businessId,
                $userId,
                $unit,
                $categoryId,
                $locations,
                $locationIds,
                $skuPrefix,
                $transactionDate
            ) {
                $product = $this->createProduct($definition, $businessId, $userId, $unit->id, $categoryId, $skuPrefix);
                $product->product_locations()->sync($locationIds);

                $this->productUtil->createVariableProductVariations(
                    $product->id,
                    $this->buildInputVariations($definition),
                    null,
                    $businessId
                );

                $product->load('variations');
                $this->seedOpeningStock($product, $locations, $businessId, $userId, $transactionDate);
            });

            $this->command?->info("VariableProductsSeeder: created '{$definition['name']}'.");
        }
    }

    private function resolveUnit(int $businessId, int $userId): Unit
    {
        $unit = Unit::where('business_id', $businessId)->orderBy('id')->first();

        if (! empty($unit)) {
            return $unit;
        }

        return Unit::create([
            'business_id' => $businessId,
            'actual_name' => 'Pieces',
            'short_name' => 'Pcs',
            'allow_decimal' => 0,
            'created_by' => $userId,
        ]);
    }

    private function createProduct(array $definition, int $businessId, int $userId, int $unitId, ?int $categoryId, string $skuPrefix): Product
    {
        $product = Product::create([
            'name' => $definition['name'],
            'business_id' => $businessId,
            'type' => 'variable',
            'unit_id' => $unitId,
            'category_id' => $categoryId,
            'tax_type' => 'exclusive',
            'tax' => null,
            'enable_stock' => 1,
            'alert_quantity' => 5,
            'sku' => ' ',
            'barcode_type' => 'C128',
            'is_inactive' => 0,
            'not_for_selling' => 0,
            'product_description' => $definition['description'],
            'created_by' => $userId,
        ]);

        // Generate the SKU from the new id without relying on session state.
        $product->sku = $skuPrefix.str_pad((string) $product->id, 4, '0', STR_PAD_LEFT);
        $product->save();

        return $product;
    }

    /**
     * Shape expected by ProductUtil::createVariableProductVariations().
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildInputVariations(array $definition): array
    {
        $variations = [];
        foreach ($definition['values'] as $value) {
            $variations[] = [
                'value' => $value['value'],
                'default_purchase_price' => $value['purchase_price'],
                'dpp_inc_tax' => $value['purchase_price'],
                'profit_percent' => $value['profit_percent'],
                'default_sell_price' => $value['sell_price'],
                'sell_price_inc_tax' => $value['sell_price'],
            ];
        }

        return [
            [
                'name' => $definition['template'],
                'variations' => $variations,
            ],
        ];
    }

    /**
     * Create per-location opening stock (PurchaseLine + opening_stock
     * Transaction) and adjust VariationLocationDetails. A deterministic
     * formula leaves some location/variation combos at 0 to exercise the
     * out-of-stock path on the storefront availability modal.
     */
    private function seedOpeningStock(Product $product, $locations, int $businessId, int $userId, string $transactionDate): void
    {
        foreach ($locations->values() as $locIndex => $location) {
            foreach ($product->variations->values() as $varIndex => $variation) {
                $qty = 20 - ($locIndex * 7) - ($varIndex * 3);
                if ($qty <= 0) {
                    continue;
                }

                $purchasePrice = (float) $variation->default_purchase_price;

                $this->productUtil->updateProductQuantity(
                    $location->id,
                    $product->id,
                    $variation->id,
                    $qty,
                    0,
                    null,
                    false
                );

                $purchaseLine = new PurchaseLine();
                $purchaseLine->product_id = $product->id;
                $purchaseLine->variation_id = $variation->id;
                $purchaseLine->quantity = $qty;
                $purchaseLine->pp_without_discount = $purchasePrice;
                $purchaseLine->purchase_price = $purchasePrice;
                $purchaseLine->purchase_price_inc_tax = $purchasePrice;
                $purchaseLine->item_tax = 0;
                $purchaseLine->tax_id = null;

                $transaction = Transaction::create([
                    'type' => 'opening_stock',
                    'opening_stock_product_id' => $product->id,
                    'status' => 'received',
                    'business_id' => $businessId,
                    'transaction_date' => $transactionDate,
                    'total_before_tax' => $purchasePrice,
                    'location_id' => $location->id,
                    'final_total' => $purchasePrice * $qty,
                    'payment_status' => 'paid',
                    'created_by' => $userId,
                ]);

                $transaction->purchase_lines()->saveMany([$purchaseLine]);
            }
        }
    }

    /**
     * The 3 demo products: a color, a size, and an edition variation set.
     *
     * @return array<int, array<string, mixed>>
     */
    private function productDefinitions(): array
    {
        return [
            [
                'name' => 'Demo Wireless Controller',
                'description' => 'Demo variable product seeded for storefront testing.',
                'template' => 'Color',
                'values' => [
                    ['value' => 'Black', 'purchase_price' => 600, 'sell_price' => 899, 'profit_percent' => 49.83],
                    ['value' => 'White', 'purchase_price' => 600, 'sell_price' => 899, 'profit_percent' => 49.83],
                    ['value' => 'Red', 'purchase_price' => 650, 'sell_price' => 949, 'profit_percent' => 46.0],
                ],
            ],
            [
                'name' => 'Demo Gaming T-Shirt',
                'description' => 'Demo variable apparel product seeded for storefront testing.',
                'template' => 'Size',
                'values' => [
                    ['value' => 'Small', 'purchase_price' => 150, 'sell_price' => 299, 'profit_percent' => 99.33],
                    ['value' => 'Medium', 'purchase_price' => 150, 'sell_price' => 299, 'profit_percent' => 99.33],
                    ['value' => 'Large', 'purchase_price' => 160, 'sell_price' => 319, 'profit_percent' => 99.38],
                    ['value' => 'XL', 'purchase_price' => 170, 'sell_price' => 339, 'profit_percent' => 99.41],
                ],
            ],
            [
                'name' => 'Demo Collector Game',
                'description' => 'Demo variable game edition product seeded for storefront testing.',
                'template' => 'Edition',
                'values' => [
                    ['value' => 'Standard', 'purchase_price' => 900, 'sell_price' => 1299, 'profit_percent' => 44.33],
                    ['value' => 'Deluxe', 'purchase_price' => 1200, 'sell_price' => 1699, 'profit_percent' => 41.58],
                ],
            ],
        ];
    }
}
