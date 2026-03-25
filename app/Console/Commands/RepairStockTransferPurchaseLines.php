<?php

namespace App\Console\Commands;

use App\PurchaseLine;
use App\Transaction;
use App\TransactionSellLine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fixes destination purchase_transfer lines that are missing or not linked to sell lines
 * (e.g. duplicate SKU bug). Does not change variation_location_details or physical stock.
 *
 * Strategy (conservative):
 * 1) For each sell line (ordered by id), if a purchase_line already has transaction_sell_line_id = sell_line.id, skip.
 * 2) Else try to claim the first unlinked purchase_line on the same transfer with matching product_id + variation_id.
 * 3) Else insert a new purchase_line from the sell line (quantity_sold left at 0 — review COGS if needed).
 *
 * Does not delete rows. Warns when sell line count < purchase line count.
 */
class RepairStockTransferPurchaseLines extends Command
{
    protected $signature = 'stock-transfer:repair-purchase-lines
                            {--dry-run : Show changes without saving}
                            {--sell-transfer-id= : Process only this sell_transfer id}
                            {--ref= : Process only sell_transfer with this ref_no (exact)}';

    protected $description = 'Repair missing/unlinked purchase_lines on stock transfer purchase_transfer transactions (safe for duplicate SKUs)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlyId = $this->option('sell-transfer-id');
        $onlyRef = $this->option('ref');

        $query = Transaction::where('type', 'sell_transfer')->orderBy('id');

        if (! empty($onlyId)) {
            $query->where('id', (int) $onlyId);
        }
        if (! empty($onlyRef)) {
            $query->where('ref_no', $onlyRef);
        }

        $sellTransfers = $query->get();

        $linked = 0;
        $inserted = 0;
        $skippedTransfers = 0;
        $warnings = 0;

        foreach ($sellTransfers as $sellTransfer) {
            $purchaseTransfer = Transaction::where('transfer_parent_id', $sellTransfer->id)
                ->where('type', 'purchase_transfer')
                ->first();

            if (empty($purchaseTransfer)) {
                continue;
            }

            $sellLines = $sellTransfer->sell_lines()->orderBy('id')->get();
            $purchaseLines = $purchaseTransfer->purchase_lines()->orderBy('id')->get();

            if ($sellLines->isEmpty()) {
                continue;
            }

            if ($purchaseLines->count() < $sellLines->count()) {
                $this->warn(sprintf(
                    'Transfer id=%s ref=%s: fewer purchase_lines (%d) than sell_lines (%d) — will insert missing.',
                    $sellTransfer->id,
                    $sellTransfer->ref_no ?? '',
                    $purchaseLines->count(),
                    $sellLines->count()
                ));
                $warnings++;
            } elseif ($purchaseLines->count() > $sellLines->count()) {
                $this->warn(sprintf(
                    'Transfer id=%s ref=%s: more purchase_lines (%d) than sell_lines (%d) — not deleting; review manually.',
                    $sellTransfer->id,
                    $sellTransfer->ref_no ?? '',
                    $purchaseLines->count(),
                    $sellLines->count()
                ));
                $warnings++;
                $skippedTransfers++;

                continue;
            }

            $result = $this->repairOneTransfer($sellTransfer, $purchaseTransfer, $sellLines, $dryRun);
            $linked += $result['linked'];
            $inserted += $result['inserted'];
        }

        $this->newLine();
        $this->info($dryRun ? 'Dry run complete.' : 'Repair complete.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Purchase lines linked (NULL FK set)', $linked],
                ['Purchase lines inserted', $inserted],
                ['Transfers skipped (extra purchase lines)', $skippedTransfers],
                ['Warnings', $warnings],
            ]
        );

        if ($dryRun) {
            $this->comment('Re-run without --dry-run to apply changes.');
        } elseif ($inserted > 0) {
            $this->warn('Inserted purchase lines use quantity_sold=0. Review COGS / FIFO (transaction_sell_lines_purchase_lines) if sales already consumed stock from those batches.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{linked: int, inserted: int}
     */
    private function repairOneTransfer(
        Transaction $sellTransfer,
        Transaction $purchaseTransfer,
        $sellLines,
        bool $dryRun
    ): array {
        $linked = 0;
        $inserted = 0;

        $usedPurchaseLineIds = [];

        $run = function () use ($sellTransfer, $purchaseTransfer, $sellLines, $dryRun, &$linked, &$inserted, &$usedPurchaseLineIds) {
            foreach ($sellLines as $sellLine) {
                $already = PurchaseLine::where('transaction_id', $purchaseTransfer->id)
                    ->where('transaction_sell_line_id', $sellLine->id)
                    ->exists();

                if ($already) {
                    continue;
                }

                $claim = PurchaseLine::where('transaction_id', $purchaseTransfer->id)
                    ->whereNull('transaction_sell_line_id')
                    ->where('product_id', $sellLine->product_id)
                    ->where('variation_id', $sellLine->variation_id)
                    ->whereNotIn('id', $usedPurchaseLineIds)
                    ->orderBy('id')
                    ->first();

                if ($claim) {
                    $usedPurchaseLineIds[] = $claim->id;
                    if (! $dryRun) {
                        $claim->transaction_sell_line_id = $sellLine->id;
                        $claim->save();
                    }
                    $linked++;
                    $this->line(sprintf(
                        'Link purchase_line id=%s -> sell_line id=%s (sell_transfer id=%s ref=%s)',
                        $claim->id,
                        $sellLine->id,
                        $sellTransfer->id,
                        $sellTransfer->ref_no ?? ''
                    ));

                    continue;
                }

                if (! $dryRun) {
                    $this->insertPurchaseLineFromSellLine($sellLine, $purchaseTransfer);
                }
                $inserted++;
                $this->line(sprintf(
                    '%s purchase_line from sell_line id=%s (sell_transfer id=%s ref=%s)',
                    $dryRun ? 'Would insert' : 'Inserted',
                    $sellLine->id,
                    $sellTransfer->id,
                    $sellTransfer->ref_no ?? ''
                ));
            }
        };

        if ($dryRun) {
            $run();
        } else {
            DB::transaction($run);
        }

        return ['linked' => $linked, 'inserted' => $inserted];
    }

    private function insertPurchaseLineFromSellLine(TransactionSellLine $sellLine, Transaction $purchaseTransfer): PurchaseLine
    {
        return PurchaseLine::create([
            'transaction_id' => $purchaseTransfer->id,
            'transaction_sell_line_id' => $sellLine->id,
            'product_id' => $sellLine->product_id,
            'variation_id' => $sellLine->variation_id,
            'quantity' => $sellLine->quantity,
            'secondary_unit_quantity' => $sellLine->secondary_unit_quantity ?? 0,
            'pp_without_discount' => 0,
            'discount_percent' => 0,
            'purchase_price' => $sellLine->unit_price ?? 0,
            'purchase_price_inc_tax' => $sellLine->unit_price_inc_tax ?? $sellLine->unit_price ?? 0,
            'item_tax' => $sellLine->item_tax ?? 0,
            'tax_id' => $sellLine->tax_id,
            'quantity_sold' => 0,
            'quantity_adjusted' => 0,
            'quantity_returned' => 0,
            'po_quantity_purchased' => 0,
            'mfg_quantity_used' => 0,
            'sub_unit_id' => $sellLine->sub_unit_id,
        ]);
    }
}
