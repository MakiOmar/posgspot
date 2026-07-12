<?php

namespace App\Console\Commands;

use App\Services\Storefront\DigitalFulfillmentService;
use App\StorefrontDigitalFulfillment;
use App\Transaction;
use Illuminate\Console\Command;

/**
 * Retry Accounts allocation for paid storefront digital orders still pending/failed.
 */
class FulfillDigitalStorefrontOrders extends Command
{
    protected $signature = 'storefront:fulfill-digital {--transaction= : Optional transaction id}';

    protected $description = 'Allocate Accounts credentials for paid storefront digital orders (staff_note + sell line)';

    public function handle(DigitalFulfillmentService $fulfillment): int
    {
        $txId = $this->option('transaction');
        $query = StorefrontDigitalFulfillment::query()
            ->whereIn('status', ['pending', 'failed'])
            ->orderBy('id');
        if ($txId !== null && $txId !== '') {
            $query->where('transaction_id', (int) $txId);
        }

        $seen = [];
        $done = 0;
        foreach ($query->get() as $row) {
            $tid = (int) $row->transaction_id;
            if (isset($seen[$tid])) {
                continue;
            }
            $seen[$tid] = true;
            $tx = Transaction::find($tid);
            if (! $tx || strtolower(trim((string) $tx->payment_status)) !== 'paid') {
                continue;
            }
            $allocated = $fulfillment->fulfillPaidTransaction($tx->fresh(['contact', 'sell_lines']));
            if ($allocated > 0) {
                $done += $allocated;
                $this->info("Allocated {$allocated} line(s) for invoice {$tx->invoice_no} (tx {$tx->id}).");
            }
        }

        // Also refresh Staff note from secrets (covers allocated rows that still show N/A).
        $syncQuery = StorefrontDigitalFulfillment::query()
            ->where('status', 'allocated')
            ->orderBy('id');
        if ($txId !== null && $txId !== '') {
            $syncQuery->where('transaction_id', (int) $txId);
        }
        $synced = 0;
        $stamped = 0;
        $syncSeen = [];
        foreach ($syncQuery->get() as $row) {
            $tid = (int) $row->transaction_id;
            if (isset($syncSeen[$tid])) {
                continue;
            }
            $syncSeen[$tid] = true;
            $tx = Transaction::find($tid);
            if (! $tx) {
                continue;
            }
            $n = $fulfillment->syncStaffNotesFromSecrets($tx);
            if ($n > 0) {
                $synced += $n;
                $this->info("Synced staff_note for invoice {$tx->invoice_no} (tx {$tx->id}).");
            }
            if ($fulfillment->stampAccountsOrderAsSentToPos($tx)) {
                $stamped++;
                $this->info("Stamped Accounts order as sent to POS for invoice {$tx->invoice_no} (tx {$tx->id}).");
            }
        }

        $this->info("Done. Newly allocated lines: {$done}. Staff notes synced: {$synced}. POS stamps: {$stamped}.");

        return self::SUCCESS;
    }
}
