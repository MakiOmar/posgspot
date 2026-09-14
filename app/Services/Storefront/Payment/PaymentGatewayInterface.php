<?php

namespace App\Services\Storefront\Payment;

use App\Transaction;
use Symfony\Component\HttpFoundation\Response;

interface PaymentGatewayInterface
{
    public function name(): string;

    /**
     * Whether the stored gateway config has the credentials this driver needs.
     *
     * @param  array<string, mixed>  $gatewayConfig
     */
    public function isConfigured(array $gatewayConfig): bool;

    /**
     * Overlay transaction-scoped fields (e.g. Geidea mode) onto gateway config.
     * Drivers must never fall back to a different credential pair than the one
     * recorded on the transaction.
     *
     * @param  array<string, mixed>  $gatewayConfig
     * @return array<string, mixed>
     */
    public function configForTransaction(Transaction $transaction, array $gatewayConfig): array;

    /**
     * Merchant order reference from a webhook or return payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function extractMerchantReference(array $payload): ?string;

    /**
     * @param  array<string, mixed>  $gatewayConfig
     * @return array<string, mixed> Client-safe charge session for hosted checkout SDK.
     */
    public function buildChargeSession(
        Transaction $transaction,
        array $gatewayConfig,
        string $returnUrl,
        string $locale,
    ): array;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $gatewayConfig
     */
    public function verifyReturnPayload(array $payload, array $gatewayConfig): bool;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $gatewayConfig
     */
    public function verifyWebhookPayload(array $payload, array $gatewayConfig): bool;

    /**
     * Live status lookup at the provider (used when the return payload is thin).
     *
     * @param  array<string, mixed>  $gatewayConfig
     * @return array<string, mixed>|null
     */
    public function fetchStatus(string $merchantRef, array $gatewayConfig): ?array;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function applyPaymentResult(Transaction $transaction, array $payload, int $businessId): PaymentResult;

    public function webhookResponse(PaymentResult $result): Response;
}
