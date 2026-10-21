<?php

namespace App\Services\Accounts;

use App\StorefrontDigitalFulfillment;
use App\TransactionSellLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Accounts undid an order (account / card back in stock, order deleted): unfetch the matching
 * sales-order / POS sell line so staff can fetch a new account with "Fetch account now".
 *
 * The ledger row is deleted rather than set to pending, so the retry command does not refetch
 * on its own; the next fetch recreates it and Accounts allocates a fresh order for the same key.
 */
class AccountsUndoResetService
{
    public function __construct(private AccountsAllocationService $allocator)
    {
    }

    /**
     * @return bool true when an allocated row was reset
     */
    public function reset(int $businessId, string $storefrontOrderId, string $lineKey, ?int $accountsOrderId): bool
    {
        return DB::transaction(function () use ($businessId, $storefrontOrderId, $lineKey, $accountsOrderId) {
            $row = StorefrontDigitalFulfillment::query()
                ->where('business_id', $businessId)
                ->where('storefront_order_id', $storefrontOrderId)
                ->where('line_key', $lineKey)
                ->lockForUpdate()
                ->first();

            if (! $row || $row->status !== 'allocated') {
                return false;
            }
            // A different id means the line was already refetched; this undo is for the old allocation.
            if ($accountsOrderId !== null && ! empty($row->accounts_order_id)
                && (int) $row->accounts_order_id !== $accountsOrderId) {
                return false;
            }

            $this->revertNotes($row);
            $row->delete();

            Log::info('Accounts undo reset POS line', [
                'transaction_id' => $row->transaction_id,
                'storefront_order_id' => $storefrontOrderId,
                'line_key' => $lineKey,
                'accounts_order_id' => $row->accounts_order_id,
            ]);

            return true;
        });
    }

    /** Same notes `SalesOrderDigitalFulfillmentService::writeNotes` filled: the order and converted invoices. */
    private function revertNotes(StorefrontDigitalFulfillment $row): void
    {
        $secrets = $row->readSecrets() ?? [];
        $title = (string) ($row->request_meta['title'] ?? $row->line_key);
        $undoneLine = __('lang_v1.account_undone_in_accounts_note', [
            'title' => $title,
            'account' => (string) ($secrets['email'] ?? ($secrets['code'] ?? 'N/A')),
        ]);

        $targets = [[(int) $row->transaction_id, $row->sell_line_id]];
        $converted = TransactionSellLine::query()
            ->where('so_line_id', $row->sell_line_id)
            ->whereNotNull('so_line_id')
            ->get(['id', 'transaction_id']);
        foreach ($converted as $line) {
            $targets[] = [(int) $line->transaction_id, (int) $line->id];
        }

        foreach ($targets as [$transactionId, $sellLineId]) {
            if ($secrets !== []) {
                $this->allocator->revertSecretsInStaffNote($transactionId, $row, $secrets);
                $this->allocator->removeSecretsFromSellLineNote($sellLineId, $secrets);
            }
            $this->allocator->appendStaffNoteLine($transactionId, $undoneLine);
        }
    }
}
