<?php

namespace App\Console\Commands;

use App\Business;
use App\Exceptions\PurchaseSellMismatch;
use App\PurchaseLine;
use App\Transaction;
use App\TransactionSellLinesPurchaseLines;
use App\Utils\TransactionUtil;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the purchase-line mappings of stock adjustments damaged by the mapPurchaseSell
 * re-insert bug, and recomputes purchase_lines.quantity_adjusted for the affected variations
 * at the adjustment's location from the surviving mapping rows.
 *
 * Sales left on over-used purchase lines are moved to other purchase lines of the same variation.
 * Does not change variation_location_details (physical stock).
 * Everything runs in one DB transaction; any line that cannot be mapped rolls the whole run back.
 */
class RepairStockAdjustmentMappings extends Command
{
    protected $signature = 'stock-adjustment:repair-mappings
                            {adjustment_id* : stock_adjustment transaction id(s) to rebuild}
                            {--dry-run : Show the result, then roll back}';

    protected $description = 'Rebuild stock adjustment purchase-line mappings and recompute quantity_adjusted';

    public function handle(TransactionUtil $transactionUtil): int
    {
        $ids = array_map('intval', (array) $this->argument('adjustment_id'));
        $adjustments = Transaction::where('type', 'stock_adjustment')->whereIn('id', $ids)
            ->with('stock_adjustment_lines')->orderBy('id')->get();

        $missing = array_diff($ids, $adjustments->pluck('id')->all());
        if (! empty($missing)) {
            $this->error('Not stock adjustments: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $failures = [];

        DB::beginTransaction();
        try {
            foreach ($adjustments as $adjustment) {
                $failures = array_merge($failures, $this->rebuild($adjustment, $transactionUtil));
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        if ($dryRun || ! empty($failures)) {
            DB::rollBack();
            foreach ($failures as $failure) {
                $this->error($failure);
            }
            $this->comment(! empty($failures) ? 'Rolled back: some lines could not be mapped.' : 'Dry run: rolled back. Re-run without --dry-run to apply.');

            return empty($failures) ? self::SUCCESS : self::FAILURE;
        }

        DB::commit();
        $this->info('Repair committed.');

        return self::SUCCESS;
    }

    /** @return array<int, string> failure messages */
    private function rebuild(Transaction $adjustment, TransactionUtil $transactionUtil): array
    {
        $lines = $adjustment->stock_adjustment_lines->sortBy('id')->values();
        $lineIds = $lines->pluck('id')->all();
        $purchaseLines = $this->purchaseLinesAtLocation($adjustment->location_id, $lines->pluck('variation_id')->unique()->all());
        $before = $this->snapshot($purchaseLines->pluck('id')->all());
        $mappedBefore = $this->mappedPerLine($lineIds);

        TransactionSellLinesPurchaseLines::whereIn('stock_adjustment_line_id', $lineIds)->delete();
        $this->recomputeQuantityAdjusted($purchaseLines);

        $business = Business::findOrFail($adjustment->business_id)->toArray();
        $business['location_id'] = $adjustment->location_id;

        $failures = [];
        foreach ($lines as $line) {
            try {
                $transactionUtil->mapPurchaseSell($business, [$line], 'stock_adjustment', false);
            } catch (PurchaseSellMismatch $e) {
                $failures[] = "Adjustment {$adjustment->id} line {$line->id}: ".$e->getMessage();
            }
        }
        $failures = array_merge($failures, $this->rebalanceOverusedLines($purchaseLines->pluck('id')->all(), $transactionUtil));

        $this->report($adjustment, $lines, $mappedBefore, $this->mappedPerLine($lineIds), $before, $this->snapshot(array_keys($before)));

        return $failures;
    }

    private function purchaseLinesAtLocation(int $locationId, array $variationIds): Collection
    {
        return PurchaseLine::join('transactions as t', 't.id', '=', 'purchase_lines.transaction_id')
            ->where('t.location_id', $locationId)
            ->whereIn('purchase_lines.variation_id', $variationIds)
            ->select('purchase_lines.*')
            ->lockForUpdate()
            ->get();
    }

    /** quantity_adjusted must equal the sum of the stock-adjustment rows still mapped to the purchase line. */
    private function recomputeQuantityAdjusted(Collection $purchaseLines): void
    {
        $mapped = TransactionSellLinesPurchaseLines::whereIn('purchase_line_id', $purchaseLines->pluck('id'))
            ->whereNotNull('stock_adjustment_line_id')
            ->groupBy('purchase_line_id')
            ->pluck(DB::raw('SUM(quantity)'), 'purchase_line_id');

        foreach ($purchaseLines as $purchaseLine) {
            PurchaseLine::where('id', $purchaseLine->id)
                ->update(['quantity_adjusted' => (float) ($mapped[$purchaseLine->id] ?? 0)]);
        }
    }

    /**
     * The bogus negative quantity_adjusted let FIFO keep selling from purchase lines that were already
     * used up. Once it is corrected those lines are over-used; move the excess onto other purchase
     * lines with the same routine POS runs after a purchase is edited.
     *
     * @return array<int, string> failure messages
     */
    private function rebalanceOverusedLines(array $purchaseLineIds, TransactionUtil $transactionUtil): array
    {
        $transactionIds = PurchaseLine::whereIn('id', $purchaseLineIds)
            ->whereRaw('quantity_sold + quantity_adjusted + quantity_returned + mfg_quantity_used > quantity')
            ->distinct()
            ->pluck('transaction_id');

        $failures = [];
        foreach (Transaction::whereIn('id', $transactionIds)->get() as $transaction) {
            try {
                $transactionUtil->adjustMappingPurchaseSellAfterEditingPurchase('received', $transaction, collect());
            } catch (PurchaseSellMismatch $e) {
                $failures[] = "Rebalancing {$transaction->type} {$transaction->id}: ".$e->getMessage();
            }
        }

        return $failures;
    }

    private function snapshot(array $purchaseLineIds): array
    {
        return PurchaseLine::whereIn('id', $purchaseLineIds)->orderBy('id')
            ->get(['id', 'product_id', 'quantity', 'quantity_sold', 'quantity_adjusted', 'quantity_returned', 'mfg_quantity_used'])
            ->keyBy('id')
            ->map(fn ($pl) => $pl->only(['product_id', 'quantity', 'quantity_sold', 'quantity_adjusted', 'quantity_returned', 'mfg_quantity_used']))
            ->all();
    }

    private function mappedPerLine(array $lineIds): array
    {
        return TransactionSellLinesPurchaseLines::whereIn('stock_adjustment_line_id', $lineIds)
            ->groupBy('stock_adjustment_line_id')
            ->selectRaw('stock_adjustment_line_id, COUNT(*) AS n, SUM(quantity) AS q')
            ->get()
            ->keyBy('stock_adjustment_line_id')
            ->all();
    }

    private function report(Transaction $adjustment, Collection $lines, array $mappedBefore, array $mappedAfter, array $before, array $after): void
    {
        $this->info("Stock adjustment {$adjustment->id} (location {$adjustment->location_id}, {$adjustment->transaction_date})");
        $this->table(
            ['Line', 'Product', 'Qty', 'Rows before', 'Mapped before', 'Rows after', 'Mapped after'],
            $lines->map(fn ($line) => [
                $line->id, $line->product_id, (float) $line->quantity,
                $mappedBefore[$line->id]->n ?? 0, (float) ($mappedBefore[$line->id]->q ?? 0),
                $mappedAfter[$line->id]->n ?? 0, (float) ($mappedAfter[$line->id]->q ?? 0),
            ])->all()
        );

        $rows = [];
        foreach ($before as $id => $old) {
            $new = $after[$id];
            if ((float) $old['quantity_adjusted'] === (float) $new['quantity_adjusted'] && (float) $old['quantity_sold'] === (float) $new['quantity_sold']) {
                continue;
            }
            $used = fn ($pl) => (float) $pl['quantity_sold'] + (float) $pl['quantity_adjusted'] + (float) $pl['quantity_returned'] + (float) $pl['mfg_quantity_used'];
            $rows[] = [$id, $old['product_id'], (float) $old['quantity'],
                (float) $old['quantity_sold'].' -> '.(float) $new['quantity_sold'],
                (float) $old['quantity_adjusted'].' -> '.(float) $new['quantity_adjusted'],
                ((float) $old['quantity'] - $used($old)).' -> '.((float) $new['quantity'] - $used($new)), ];
        }
        $this->table(['Purchase line', 'Product', 'Qty', 'Sold', 'Adjusted', 'Remaining'], $rows);
    }
}
