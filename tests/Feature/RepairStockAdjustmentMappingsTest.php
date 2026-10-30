<?php

namespace Tests\Feature;

use App\BusinessLocation;
use App\Product;
use App\PurchaseLine;
use App\StockAdjustmentLine;
use App\Transaction;
use App\TransactionSellLinesPurchaseLines;
use App\Variation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RepairStockAdjustmentMappingsTest extends TestCase
{
    use DatabaseTransactions;

    private const BUSINESS_ID = 1;

    private PurchaseLine $purchaseLine;

    private StockAdjustmentLine $line;

    protected function setUp(): void
    {
        parent::setUp();

        $product = Product::where('business_id', self::BUSINESS_ID)->where('enable_stock', 1)->whereHas('variations')->first();
        if (! $product) {
            $this->markTestSkipped('No stock-enabled product for business 1.');
        }
        $variation = Variation::where('product_id', $product->id)->first();
        $locationId = BusinessLocation::where('business_id', self::BUSINESS_ID)->value('id');

        $opening = $this->transaction('opening_stock', $locationId, '2000-01-01 00:00:00', ['opening_stock_product_id' => $product->id]);
        $this->purchaseLine = PurchaseLine::create([
            'transaction_id' => $opening->id, 'product_id' => $product->id, 'variation_id' => $variation->id,
            'quantity' => 10, 'quantity_adjusted' => -3,
            'pp_without_discount' => 1, 'purchase_price' => 1, 'purchase_price_inc_tax' => 1, 'item_tax' => 0,
        ]);

        $adjustment = $this->transaction('stock_adjustment', $locationId, now());
        $this->line = StockAdjustmentLine::create([
            'transaction_id' => $adjustment->id, 'product_id' => $product->id, 'variation_id' => $variation->id,
            'quantity' => 2, 'unit_price' => 1,
        ]);

        for ($i = 0; $i < 5; $i++) {
            TransactionSellLinesPurchaseLines::create([
                'stock_adjustment_line_id' => $this->line->id, 'purchase_line_id' => $this->purchaseLine->id, 'quantity' => 2,
            ]);
        }
    }

    public function test_dry_run_leaves_data_unchanged(): void
    {
        $this->artisan('stock-adjustment:repair-mappings', ['adjustment_id' => [$this->line->transaction_id], '--dry-run' => true])
            ->assertExitCode(0);

        $this->assertSame(5, TransactionSellLinesPurchaseLines::where('stock_adjustment_line_id', $this->line->id)->count());
        $this->assertEquals(-3, $this->purchaseLine->fresh()->quantity_adjusted);
    }

    public function test_repair_maps_line_once_and_recomputes_quantity_adjusted(): void
    {
        $this->artisan('stock-adjustment:repair-mappings', ['adjustment_id' => [$this->line->transaction_id]])
            ->assertExitCode(0);

        $rows = TransactionSellLinesPurchaseLines::where('stock_adjustment_line_id', $this->line->id)->get();
        $this->assertCount(1, $rows);
        $this->assertEquals(2, $rows->first()->quantity);
        $this->assertSame($this->purchaseLine->id, (int) $rows->first()->purchase_line_id);
        $this->assertEquals(2, $this->purchaseLine->fresh()->quantity_adjusted);
    }

    public function test_rejects_ids_that_are_not_stock_adjustments(): void
    {
        $this->artisan('stock-adjustment:repair-mappings', ['adjustment_id' => [$this->purchaseLine->transaction_id]])
            ->assertExitCode(1);
    }

    private function transaction(string $type, int $locationId, $date, array $extra = []): Transaction
    {
        return Transaction::create(array_merge([
            'business_id' => self::BUSINESS_ID, 'location_id' => $locationId, 'type' => $type, 'status' => 'received',
            'transaction_date' => $date, 'total_before_tax' => 0, 'final_total' => 0, 'created_by' => 1,
        ], $extra));
    }
}
