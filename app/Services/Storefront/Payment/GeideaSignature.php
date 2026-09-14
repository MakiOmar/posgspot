<?php

namespace App\Services\Storefront\Payment;

/**
 * Geidea HMAC-SHA256 signatures.
 *
 * Two recipes (WooCommerce plugin 3.6.1). Never mix field orders.
 * Key is the Merchant API Password. Output is raw HMAC then base64.
 */
class GeideaSignature
{
    /**
     * Vendor timestamp: PHP date("n/d/Y g:i:s A"), e.g. "9/14/2026 3:17:05 PM".
     * Not ISO 8601 — that silently fails signature validation.
     */
    public function timestamp(?\DateTimeInterface $at = null): string
    {
        $at = $at ?? now();

        return $at->format('n/d/Y g:i:s A');
    }

    public function formatAmount(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        return number_format((float) $amount, 2, '.', '');
    }

    public function session(
        string $merchantPublicKey,
        mixed $amount,
        string $currency,
        string $merchantReferenceId,
        string $timestamp,
        string $apiPassword,
    ): string {
        $data = $merchantPublicKey
            .$this->formatAmount($amount)
            .$currency
            .$merchantReferenceId
            .$timestamp;

        return $this->hmac($data, $apiPassword);
    }

    public function callback(
        string $merchantPublicKey,
        mixed $amount,
        string $currency,
        string $orderId,
        string $status,
        string $merchantReferenceId,
        string $timestamp,
        string $apiPassword,
    ): string {
        $data = $merchantPublicKey
            .$this->formatAmount($amount)
            .$currency
            .$orderId
            .$status
            .$merchantReferenceId
            .$timestamp;

        return $this->hmac($data, $apiPassword);
    }

    public function hmac(string $data, string $apiPassword): string
    {
        return base64_encode(hash_hmac('sha256', $data, $apiPassword, true));
    }
}
