<?php

namespace App\Services\Storefront;

use App\Services\Storefront\Payment\PaymentGatewayManager;
use App\Transaction;

/**
 * POS sell list labels for website orders paid via Fawry / Geidea (etc.).
 */
class StorefrontOrderListPaymentPresenter
{
    public function __construct(private PaymentGatewayManager $paymentGateways)
    {
    }

    public function gatewayProvider(Transaction $transaction): ?string
    {
        if (trim((string) ($transaction->storefront_order_id ?? '')) === '') {
            return null;
        }

        $meta = $this->paymentMeta($transaction);
        $provider = strtolower(trim((string) ($meta['provider'] ?? '')));

        return $provider !== '' ? $provider : null;
    }

    public function gatewayLabel(Transaction $transaction): ?string
    {
        $provider = $this->gatewayProvider($transaction);

        return $provider !== null ? $this->paymentGateways->providerLabel($provider) : null;
    }

    public function onlinePaymentLineStatus(Transaction $transaction): ?string
    {
        if ($this->gatewayProvider($transaction) === null) {
            return null;
        }

        $lines = $transaction->relationLoaded('payment_lines')
            ? $transaction->payment_lines
            : $transaction->payment_lines()->get();

        $line = $lines->firstWhere('method', 'card');
        if ($line === null) {
            return null;
        }

        $status = strtolower(trim((string) $line->payment_line_status));

        return $status !== '' ? $status : null;
    }

    /**
     * HTML snippet appended to the sells list payment method column.
     */
    public function listBadgeHtml(Transaction $transaction): string
    {
        $gatewayLabel = $this->gatewayLabel($transaction);
        if ($gatewayLabel === null) {
            return '';
        }

        $lineStatus = $this->onlinePaymentLineStatus($transaction);
        $paid = strtolower(trim((string) $transaction->payment_status)) === 'paid';

        if ($lineStatus === 'completed' || ($paid && $lineStatus !== 'pending')) {
            $statusLabel = __('restaurant.completed');
            $class = 'bg-green';
        } elseif ($lineStatus === 'pending') {
            $statusLabel = __('lang_v1.pending');
            $class = 'bg-yellow';
        } else {
            return '';
        }

        $title = __('lang_v1.storefront_gateway_payment_label', ['provider' => $gatewayLabel]);

        return ' <small class="label '.$class.' no-print storefront-gateway-payment-label" '
            .'title="'.e($title).'" '
            .'data-provider="'.e($this->gatewayProvider($transaction)).'" '
            .'data-line-status="'.e($lineStatus ?? '').'">'
            .e($statusLabel)
            .'</small>';
    }

    /**
     * @param  list<string>  $methods  Raw payment line method keys (e.g. card, cash).
     */
    public function resolvePaymentMethodLabel(Transaction $transaction, array $methods): string
    {
        $gatewayLabel = $this->gatewayLabel($transaction);
        if ($gatewayLabel === null) {
            return '';
        }

        if (count($methods) === 1 && ($methods[0] ?? '') === 'card') {
            return $gatewayLabel;
        }

        if (count($methods) === 0 && $this->onlinePaymentLineStatus($transaction) !== null) {
            return $gatewayLabel;
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentMeta(Transaction $transaction): array
    {
        $meta = $transaction->storefront_payment_meta ?? [];
        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?: [];
        }

        return is_array($meta) ? $meta : [];
    }
}
