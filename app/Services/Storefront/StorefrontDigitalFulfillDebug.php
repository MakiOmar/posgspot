<?php

namespace App\Services\Storefront;

use App\StorefrontDigitalFulfillment;
use App\Transaction;
use Illuminate\Support\Facades\Log;

/**
 * Temporary diagnostics for digital allocate-on-paid (Accounts + staff_note).
 * Always logs to laravel.log under storefront.digital.fulfill.*.
 */
class StorefrontDigitalFulfillDebug
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function log(string $stage, array $context = []): void
    {
        Log::warning('storefront.digital.fulfill.'.$stage, $context);
    }

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(Transaction $transaction): array
    {
        $rows = StorefrontDigitalFulfillment::where('transaction_id', $transaction->id)
            ->orderBy('id')
            ->get()
            ->map(fn (StorefrontDigitalFulfillment $row) => [
                'id' => $row->id,
                'line_key' => $row->line_key,
                'kind' => $row->kind,
                'status' => $row->status,
                'attempts' => $row->attempts,
                'last_error' => $row->last_error,
                'accounts_order_id' => $row->accounts_order_id,
                'sell_line_id' => $row->sell_line_id,
                'allocated_at' => optional($row->allocated_at)?->toDateTimeString(),
                'request_meta' => $row->request_meta,
                'has_secrets' => ! empty($row->secret_payload),
            ])
            ->all();

        $pending = collect($rows)->whereIn('status', ['pending', 'failed'])->count();

        return [
            'transaction_id' => $transaction->id,
            'invoice_no' => $transaction->invoice_no,
            'source' => $transaction->source,
            'storefront_order_id' => $transaction->storefront_order_id,
            'payment_status' => $transaction->payment_status,
            'final_total' => $transaction->final_total,
            'staff_note_preview' => mb_substr((string) ($transaction->staff_note ?? ''), 0, 500),
            'fulfillment_count' => count($rows),
            'pending_or_failed' => $pending,
            'would_hook_run' => strtolower(trim((string) $transaction->payment_status)) === 'paid' && $pending > 0,
            'rows' => $rows,
        ];
    }
}
