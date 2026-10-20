<?php

namespace App\Console\Commands;

use App\Services\Storefront\DigitalFulfillmentService;
use App\Services\Storefront\StorefrontMailService;
use App\StorefrontDigitalFulfillment;
use App\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Retry Accounts allocation for paid storefront digital orders still pending/failed.
 */
class FulfillDigitalStorefrontOrders extends Command
{
    protected $signature = 'storefront:fulfill-digital
        {--transaction= : Optional transaction id}
        {--recent : Scheduler mode: retry rows from the last 7 days, sync notes for lines allocated in the last hour}';

    protected $description = 'Allocate Accounts credentials for paid storefront digital orders (staff_note + sell line)';

    public function handle(DigitalFulfillmentService $fulfillment): int
    {
        $txId = $this->option('transaction');
        // A run for one transaction is staff-initiated, so it may retry past the attempts cap.
        $manual = $txId !== null && $txId !== '';
        // so-* / pos-* rows belong to sales orders and POS sells (accounts:fulfill-sales-orders).
        $query = StorefrontDigitalFulfillment::query()
            ->tap(fn ($q) => DigitalFulfillmentService::whereRetryable($q, $manual))
            ->where('storefront_order_id', 'not like', 'so-%')
            ->where('storefront_order_id', 'not like', 'pos-%')
            ->orderBy('id');
        if ($manual) {
            $query->where('transaction_id', (int) $txId);
        } elseif ($this->option('recent')) {
            // Unpaid checkouts keep pending rows forever; the paid hook still handles late payments.
            $query->where('created_at', '>=', now()->subDays(7));
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
            $allocated = $fulfillment->fulfillPaidTransaction($tx->fresh(['contact', 'sell_lines']), $manual);
            if ($allocated > 0) {
                $done += $allocated;
                $this->info("Allocated {$allocated} line(s) for invoice {$tx->invoice_no} (tx {$tx->id}).");
                $this->sendConfirmation($tx);
            }
        }

        // Also refresh Staff note from secrets (covers allocated rows that still show N/A).
        $syncQuery = StorefrontDigitalFulfillment::query()
            ->where('status', 'allocated')
            ->where('storefront_order_id', 'not like', 'so-%')
            ->where('storefront_order_id', 'not like', 'pos-%')
            ->orderBy('id');
        if ($manual) {
            $syncQuery->where('transaction_id', (int) $txId);
        } elseif ($this->option('recent')) {
            $syncQuery->where('allocated_at', '>=', now()->subHour());
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

    /** Lines that waited on a supplier order were not in the email sent when payment landed. */
    private function sendConfirmation(Transaction $tx): void
    {
        try {
            app(StorefrontMailService::class)->sendPaidDigitalConfirmation($tx->fresh(['contact', 'sell_lines']));
        } catch (\Throwable $e) {
            Log::warning('Storefront digital confirmation after retry failed: '.$e->getMessage(), [
                'transaction_id' => $tx->id,
            ]);
        }
    }
}
