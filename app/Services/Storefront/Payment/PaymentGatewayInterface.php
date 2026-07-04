<?php

namespace App\Services\Storefront\Payment;

use App\Transaction;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface PaymentGatewayInterface
{
    public function name(): string;

    /**
     * @return array<string, mixed> Client-safe charge session for hosted checkout SDK.
     */
    public function buildChargeSession(
        Transaction $transaction,
        array $gatewayConfig,
        string $returnUrl,
        string $locale,
    ): array;

    public function verifyReturnPayload(array $payload, array $gatewayConfig): bool;

    public function verifyWebhookPayload(array $payload, array $gatewayConfig): bool;

    /**
     * @return array<string, mixed>|null Fawry status API response.
     */
    public function fetchStatus(string $merchantRef, array $gatewayConfig): ?array;

    public function applyPaymentResult(Transaction $transaction, array $payload, int $businessId): PaymentResult;

    public function webhookResponse(PaymentResult $result): Response;
}
