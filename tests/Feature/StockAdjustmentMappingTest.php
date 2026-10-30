<?php

namespace Tests\Feature;

use App\Business;
use App\BusinessLocation;
use App\Product;
use App\PurchaseLine;
use App\StockAdjustmentLine;
use App\Transaction;
use App\TransactionSellLinesPurchaseLines;
use App\Utils\TransactionUtil;
use App\Variation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Mapping several stock adjustment lines in one call once re-inserted every earlier line's rows,
 * which grew transaction_sell_lines_purchase_lines quadratically and later made an Opening Stock
 * save run for hours.
 */
class StockAdjustmentMappingTest extends TestCase
{
    use DatabaseTransactions;

    private const BUSINESS_ID = 1;

    public function test_each_adjustment_line_is_mapped_exactly_once(): void
    {
        $product = Product::where('business_id', self::BUSINESS_ID)->where('enable_stock', 1)->whereHas('variations')->first();
        if (! $product) {
            $this->markTestSkipped('No stock-enabled product for business 1.');
        }
        $variation = Variation::where('product_id', $product->id)->first();
        $locationId = BusinessLocation::where('business_id', self::BUSINESS_ID)->value('id');

        $this->openingStock($product, $variation, $locationId, 10);
        $lines = $this->adjustmentLines($product, $variation, $locationId, 3);

        $business = Business::find(self::BUSINESS_ID)->toArray();
        $business['location_id'] = $locationId;
        $business['accounting_method'] = 'fifo';

        app(TransactionUtil::class)->mapPurchaseSell($business, $lines, 'stock_adjustment', false);

        $lineIds = collect($lines)->pluck('id');
        $this->assertSame(3, TransactionSellLinesPurchaseLines::whereIn('stock_adjustment_line_id', $lineIds)->count());
        foreach ($lines as $line) {
            $this->assertEquals(1, TransactionSellLinesPurchaseLines::where('stock_adjustment_line_id', $line->id)->sum('quantity'));
        }
    }

    /** Dated far in the past so FIFO allocates from this purchase line before any existing stock. */
    private function openingStock(Product $product, Variation $variation, int $locationId, float $qty): PurchaseLine
    {
        $transaction = Transaction::create([
            'business_id' => self::BUSINESS_ID,
            'location_id' => $locationId,
            'type' => 'opening_stock',
            'status' => 'received',
            'opening_stock_product_id' => $product->id,
            'transaction_date' => '2000-01-01 00:00:00',
            'total_before_tax' => 0,
            'final_total' => 0,
            'payment_status' => 'paid',
            'created_by' => 1,
        ]);

        return PurchaseLine::create([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'variation_id' => $variation->id,
            'quantity' => $qty,
            'pp_without_discount' => 1,
            'purchase_price' => 1,
            'purchase_price_inc_tax' => 1,
            'item_tax' => 0,
        ]);
    }

    /** @return array<int, StockAdjustmentLine> */
    private function adjustmentLines(Product $product, Variation $variation, int $locationId, int $count): array
    {
        $adjustment = Transaction::create([
            'business_id' => self::BUSINESS_ID,
            'location_id' => $locationId,
            'type' => 'stock_adjustment',
            'status' => 'received',
            'transaction_date' => now(),
            'total_before_tax' => 0,
            'final_total' => 0,
            'created_by' => 1,
        ]);

        $lines = [];
        for ($i = 0; $i < $count; $i++) {
            $lines[] = StockAdjustmentLine::create([
                'transaction_id' => $adjustment->id,
                'product_id' => $product->id,
                'variation_id' => $variation->id,
                'quantity' => 1,
                'unit_price' => 1,
            ]);
        }

        return $lines;
    }
}
