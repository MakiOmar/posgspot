<?php

namespace Tests\Feature;

use App\BusinessLocation;
use App\Product;
use App\PurchaseLine;
use App\Transaction;
use App\Unit;
use App\Utils\ProductUtil;
use App\VariationLocationDetails;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The POS sell screen must offer only quantity that invoice save can allocate.
 * Pending transfers and the on-hand counter are not that quantity.
 */
class SellableQuantityTest extends TestCase
{
    use DatabaseTransactions;

    private const BUSINESS_ID = 1;

    public function test_sellable_quantity_ignores_pending_transfers_and_the_on_hand_counter(): void
    {
        $locationId = BusinessLocation::where('business_id', self::BUSINESS_ID)->value('id');
        if (! $locationId) {
            $this->markTestSkipped('No location for business 1.');
        }
        $unitId = Unit::where('business_id', self::BUSINESS_ID)->value('id');
        if (! $unitId) {
            $this->markTestSkipped('No unit for business 1.');
        }

        $sku = 'SELLABLE-'.random_int(100000, 999999);
        $product = Product::create([
            'name' => 'Sellable qty fixture',
            'business_id' => self::BUSINESS_ID,
            'type' => 'single',
            'unit_id' => $unitId,
            'tax_type' => 'exclusive',
            'enable_stock' => 1,
            'alert_quantity' => 0,
            'sku' => $sku,
            'barcode_type' => 'C128',
            'created_by' => 1,
            'is_inactive' => 0,
            'not_for_selling' => 0,
        ]);
        app(ProductUtil::class)->createSingleProductVariation($product, $sku, 100, 100, 0, 100, 100);
        $variation = $product->variations()->first();

        VariationLocationDetails::create([
            'product_id' => $product->id,
            'product_variation_id' => $variation->product_variation_id,
            'variation_id' => $variation->id,
            'location_id' => $locationId,
            'qty_available' => 9,
        ]);

        $this->stockLine($product->id, $variation->id, $locationId, 'opening_stock', 'received', 2, 1);
        $this->stockLine($product->id, $variation->id, $locationId, 'purchase_transfer', 'pending', 5, 0);
        $this->stockLine($product->id, $variation->id, $locationId, 'purchase', 'received', 1, 3);

        $sellable = app(ProductUtil::class)->sellableQtyByVariation(self::BUSINESS_ID, (int) $locationId, [$variation->id]);

        $this->assertSame(1.0, $sellable[$variation->id]);
    }

    private function stockLine(int $productId, int $variationId, int $locationId, string $type, string $status, float $quantity, float $quantitySold): void
    {
        $transaction = Transaction::create([
            'business_id' => self::BUSINESS_ID,
            'location_id' => $locationId,
            'type' => $type,
            'status' => $status,
            'transaction_date' => '2000-01-01 00:00:00',
            'total_before_tax' => 0,
            'final_total' => 0,
            'payment_status' => 'paid',
            'created_by' => 1,
        ]);

        PurchaseLine::create([
            'transaction_id' => $transaction->id,
            'product_id' => $productId,
            'variation_id' => $variationId,
            'quantity' => $quantity,
            'quantity_sold' => $quantitySold,
            'pp_without_discount' => 1,
            'purchase_price' => 1,
            'purchase_price_inc_tax' => 1,
            'item_tax' => 0,
        ]);
    }
}
