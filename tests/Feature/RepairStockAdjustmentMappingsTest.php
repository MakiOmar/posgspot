<?php

namespace Tests\Feature;

use App\BusinessLocation;
use App\Product;
use App\PurchaseLine;
use App\StockAdjustmentLine;
use App\Transaction;
use App\TransactionSellLine;
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

    private int $locationId;

    protected function setUp(): void
    {
        parent::setUp();

        $product = Product::where('business_id', self::BUSINESS_ID)->where('enable_stock', 1)->whereHas('variations')->first();
        if (! $product) {
            $this->markTestSkipped('No stock-enabled product for business 1.');
        }
        $variation = Variation::where('product_id', $product->id)->first();
        $locationId = $this->locationId = BusinessLocation::where('business_id', self::BUSINESS_ID)->value('id');

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

    public function test_moves_sales_off_an_overused_purchase_line(): void
    {
        $this->purchaseLine->update(['quantity_sold' => 11]);
        $this->mapSale($this->purchaseLine, 11);
        $newer = $this->newerPurchaseLine(10);

        $this->artisan('stock-adjustment:repair-mappings', ['adjustment_id' => [$this->line->transaction_id]])
            ->assertExitCode(0);

        $this->assertEquals(10, $this->purchaseLine->fresh()->quantity_sold);
        $this->assertEquals(0, $this->purchaseLine->fresh()->quantity_adjusted);
        $this->assertEquals(1, $newer->fresh()->quantity_sold);
        $this->assertEquals(2, $newer->fresh()->quantity_adjusted);
    }

    public function test_rejects_ids_that_are_not_stock_adjustments(): void
    {
        $this->artisan('stock-adjustment:repair-mappings', ['adjustment_id' => [$this->purchaseLine->transaction_id]])
            ->assertExitCode(1);
    }

    private function mapSale(PurchaseLine $purchaseLine, float $qty): void
    {
        $sell = $this->transaction('sell', $this->locationId, now(), ['status' => 'final']);
        $sellLine = TransactionSellLine::create([
            'transaction_id' => $sell->id, 'product_id' => $purchaseLine->product_id, 'variation_id' => $purchaseLine->variation_id,
            'quantity' => $qty, 'unit_price' => 1, 'unit_price_inc_tax' => 1, 'item_tax' => 0, 'line_discount_amount' => 0,
        ]);
        TransactionSellLinesPurchaseLines::create(['sell_line_id' => $sellLine->id, 'purchase_line_id' => $purchaseLine->id, 'quantity' => $qty]);
    }

    private function newerPurchaseLine(float $qty): PurchaseLine
    {
        $opening = $this->transaction('opening_stock', $this->locationId, '2000-06-01 00:00:00',
            ['opening_stock_product_id' => $this->purchaseLine->product_id]);

        return PurchaseLine::create([
            'transaction_id' => $opening->id, 'product_id' => $this->purchaseLine->product_id, 'variation_id' => $this->purchaseLine->variation_id,
            'quantity' => $qty, 'pp_without_discount' => 1, 'purchase_price' => 1, 'purchase_price_inc_tax' => 1, 'item_tax' => 0,
        ]);
    }

    private function transaction(string $type, int $locationId, $date, array $extra = []): Transaction
    {
        return Transaction::create(array_merge([
            'business_id' => self::BUSINESS_ID, 'location_id' => $locationId, 'type' => $type, 'status' => 'received',
            'transaction_date' => $date, 'total_before_tax' => 0, 'final_total' => 0, 'created_by' => 1,
        ], $extra));
    }
}
