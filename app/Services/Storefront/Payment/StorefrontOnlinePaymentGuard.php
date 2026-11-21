<?php

namespace App\Services\Storefront\Payment;

use App\Exceptions\StorefrontGatewayPaymentBlockedException;
use App\Transaction;
use App\Utils\Util;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Blocks staff from marking unpaid storefront Geidea/Fawry checkouts as paid
 * unless the gateway already wrote verified capture meta (or an audited override).
 */
class StorefrontOnlinePaymentGuard
{
    public const OVERRIDE_PERMISSION = 'storefront.override_gateway_payment';

    private const ONLINE_PROVIDERS = ['geidea', 'fawry'];

    /**
     * @return array<string, mixed>
     */
    public function paymentMeta(Transaction $transaction): array
    {
        $meta = $transaction->storefront_payment_meta ?? [];
        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?: [];
        }

        return is_array($meta) ? $meta : [];
    }

    public function isOnlineGatewayCheckout(Transaction $transaction): bool
    {
        if (trim((string) ($transaction->storefront_order_id ?? '')) === '') {
            return false;
        }

        $provider = strtolower(trim((string) ($this->paymentMeta($transaction)['provider'] ?? '')));
        if (in_array($provider, self::ONLINE_PROVIDERS, true)) {
            return true;
        }

        // Checkout wrote a pending card line before provider meta in some edge paths.
        $hasPendingCard = $transaction->payment_lines()
            ->where('method', 'card')
            ->where('payment_line_status', 'pending')
            ->exists();

        return $hasPendingCard;
    }

    public function hasVerifiedCapture(Transaction $transaction): bool
    {
        $meta = $this->paymentMeta($transaction);
        $provider = strtolower(trim((string) ($meta['provider'] ?? '')));

        if ($provider === 'geidea' || ($provider === '' && ! empty($meta['geidea_order_id']))) {
            // geidea_order_id is only written by GeideaPaymentGateway after remote Paid verify.
            return trim((string) ($meta['geidea_order_id'] ?? '')) !== '';
        }

        if ($provider === 'fawry') {
            // fawry_ref_number + PAID are written together only after remote confirm.
            return trim((string) ($meta['fawry_ref_number'] ?? '')) !== ''
                && strtoupper(trim((string) ($meta['order_status'] ?? ''))) === 'PAID';
        }

        return false;
    }

    /**
     * Reject completing online gateway payment lines without capture (or audited override).
     *
     * @param  string  $newLineStatus  Target payment_line_status (e.g. completed)
     * @param  string|null  $previousLineStatus  Existing status when editing; null when creating
     * @param  string|null  $overrideReason  Required when using override permission
     */
    public function assertMayCompleteOnlinePayment(
        Transaction $transaction,
        string $newLineStatus,
        ?string $previousLineStatus = null,
        ?string $overrideReason = null
    ): void {
        if (strtolower(trim($newLineStatus)) !== 'completed') {
            return;
        }

        if ($previousLineStatus !== null && strtolower(trim($previousLineStatus)) === 'completed') {
            return;
        }

        if (! $this->isOnlineGatewayCheckout($transaction)) {
            return;
        }

        if ($this->hasVerifiedCapture($transaction)) {
            return;
        }

        $user = Auth::user();
        $reason = trim((string) $overrideReason);
        if ($user && $user->can(self::OVERRIDE_PERMISSION) && $reason !== '') {
            $this->logOverride($transaction, $reason, $previousLineStatus, $newLineStatus);

            return;
        }

        throw new StorefrontGatewayPaymentBlockedException(
            __('lang_v1.storefront_gateway_payment_blocked')
        );
    }

    /**
     * @param  list<array<string, mixed>>  $paymentLines  POS payment[] payloads
     */
    public function assertPaymentLinesMayComplete(
        Transaction $transaction,
        array $paymentLines,
        ?string $overrideReason = null
    ): void {
        foreach ($paymentLines as $line) {
            if (! is_array($line)) {
                continue;
            }
            if (! empty($line['is_return'])) {
                continue;
            }
            $status = (string) ($line['payment_line_status'] ?? 'completed');
            $previous = null;
            if (! empty($line['payment_id'])) {
                $existing = $transaction->payment_lines()
                    ->where('id', (int) $line['payment_id'])
                    ->value('payment_line_status');
                $previous = $existing !== null ? (string) $existing : null;
            }
            $this->assertMayCompleteOnlinePayment($transaction, $status, $previous, $overrideReason);
        }
    }

    private function logOverride(
        Transaction $transaction,
        string $reason,
        ?string $previousLineStatus,
        string $newLineStatus
    ): void {
        $meta = $this->paymentMeta($transaction);
        $properties = [
            'storefront_order_id' => $transaction->storefront_order_id,
            'invoice_no' => $transaction->invoice_no,
            'reason' => $reason,
            'previous_line_status' => $previousLineStatus,
            'new_line_status' => $newLineStatus,
            'payment_meta' => $meta,
            'actor_id' => Auth::id(),
        ];

        Log::warning('Storefront gateway payment override', $properties);

        try {
            app(Util::class)->activityLog(
                $transaction,
                'storefront_gateway_payment_override',
                null,
                $properties,
                false
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to activity-log storefront gateway override: '.$e->getMessage(), [
                'transaction_id' => $transaction->id,
            ]);
        }
    }
}
