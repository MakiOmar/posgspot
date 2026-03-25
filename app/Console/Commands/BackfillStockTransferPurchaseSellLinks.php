<?php

namespace App\Console\Commands;

use App\Transaction;
use Illuminate\Console\Command;

/**
 * Sets purchase_lines.transaction_sell_line_id for stock transfers created before the duplicate-SKU fix.
 * Pairs destination purchase lines with sell lines by matching order (id ASC) when counts match.
 */
class BackfillStockTransferPurchaseSellLinks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock-transfer:backfill-purchase-line-sell-links
                            {--dry-run : Show what would be updated without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill purchase_lines.transaction_sell_line_id for purchase_transfer rows (pairs with sell lines by line order)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $sellTransfers = Transaction::where('type', 'sell_transfer')
            ->orderBy('id')
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($sellTransfers as $sellTransfer) {
            $purchaseTransfer = Transaction::where('transfer_parent_id', $sellTransfer->id)
                ->where('type', 'purchase_transfer')
                ->first();

            if (empty($purchaseTransfer)) {
                continue;
            }

            $sellLines = $sellTransfer->sell_lines()->orderBy('id')->get();
            $purchaseLines = $purchaseTransfer->purchase_lines()->orderBy('id')->get();

            if ($sellLines->count() !== $purchaseLines->count()) {
                $this->warn(sprintf(
                    'Skipped sell_transfer id=%s ref=%s: sell_lines count (%d) != purchase_lines count (%d)',
                    $sellTransfer->id,
                    $sellTransfer->ref_no ?? '',
                    $sellLines->count(),
                    $purchaseLines->count()
                ));
                $skipped++;

                continue;
            }

            foreach ($sellLines as $i => $sellLine) {
                $pl = $purchaseLines[$i];
                if ((int) $pl->transaction_sell_line_id === (int) $sellLine->id) {
                    continue;
                }

                if (! $dryRun) {
                    $pl->transaction_sell_line_id = $sellLine->id;
                    $pl->save();
                }
                $updated++;
                $this->line(sprintf(
                    'purchase_line id=%s -> transaction_sell_line_id=%s (sell_transfer id=%s)',
                    $pl->id,
                    $sellLine->id,
                    $sellTransfer->id
                ));
            }
        }

        $this->info($dryRun ? "Dry run: would update {$updated} purchase line(s)." : "Updated {$updated} purchase line(s).");
        if ($skipped > 0) {
            $this->warn("Skipped {$skipped} transfer(s) with mismatched line counts (needs manual fix).");
        }

        return self::SUCCESS;
    }
}
