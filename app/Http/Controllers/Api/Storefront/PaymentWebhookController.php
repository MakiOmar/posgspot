<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\Payment\PaymentGatewayManager;
use App\Services\Storefront\Payment\PaymentResult;
use App\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Payment gateway webhooks for storefront orders.
 */
class PaymentWebhookController extends StorefrontController
{
    public function __construct(private PaymentGatewayManager $gateways)
    {
    }

    public function handle(Request $request, string $provider)
    {
        $businessId = $this->businessId($request);
        $config = $this->gateways->gatewayConfig($businessId);

        if (($config['provider'] ?? '') !== $provider) {
            return $this->jsonError('Unknown payment provider.', 400);
        }

        try {
            $driver = $this->gateways->driver($provider);
        } catch (\InvalidArgumentException) {
            return $this->jsonError('Unknown payment provider.', 400);
        }

        $payload = $request->all();
        if (empty($payload)) {
            $raw = $request->getContent();
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        if (! $driver->verifyWebhookPayload($payload, $config)) {
            Log::warning('Storefront payment webhook invalid signature', ['provider' => $provider]);

            return $driver->webhookResponse(new PaymentResult(PaymentResult::STATUS_INVALID));
        }

        $merchantRef = $payload['merchantRefNumber'] ?? null;
        if (empty($merchantRef)) {
            return $driver->webhookResponse(new PaymentResult(PaymentResult::STATUS_INVALID, 'Missing merchant reference.'));
        }

        $transaction = Transaction::where('business_id', $businessId)
            ->where('storefront_order_id', $merchantRef)
            ->first();

        if (empty($transaction)) {
            return $driver->webhookResponse(new PaymentResult(PaymentResult::STATUS_INVALID, 'Order not found.'));
        }

        $result = $driver->applyPaymentResult($transaction->fresh(), $payload, $businessId);

        return $driver->webhookResponse($result);
    }
}
