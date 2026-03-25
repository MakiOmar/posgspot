<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockTransferDuplicateVariationTest extends TestCase
{
    /**
     * Migration adds the FK column used to pair destination purchase lines with sell lines.
     */
    public function test_purchase_lines_table_has_transaction_sell_line_id_column(): void
    {
        $this->assertTrue(Schema::hasColumn('purchase_lines', 'transaction_sell_line_id'));
    }

    /**
     * Backfill command is registered and exits successfully (dry-run does not write).
     */
    public function test_stock_transfer_backfill_command_runs(): void
    {
        $this->artisan('stock-transfer:backfill-purchase-line-sell-links', ['--dry-run' => true])
            ->assertExitCode(0);
    }
}
