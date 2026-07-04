<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\CheckoutService;
use App\Services\Storefront\Payment\PaymentGatewayManager;
use App\Services\Storefront\Payment\PaymentResult;
use App\Transaction;
use Illuminate\Http\Request;

/**
 * Confirms Fawry return URL payloads after hosted checkout.
 */
class PaymentReturnController extends StorefrontController
{
    public function __construct(
        private PaymentGatewayManager $gateways,
        private CheckoutService $checkoutService,
    ) {
    }

    public function confirm(Request $request, string $provider)
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
        if (empty($payload['merchantRefNumber'])) {
            return $this->jsonError('Missing merchant reference.', 422);
        }

        if (! $driver->verifyReturnPayload($payload, $config)) {
            return $this->jsonError('Invalid payment signature.', 422);
        }

        $transaction = Transaction::where('business_id', $businessId)
            ->where('storefront_order_id', $payload['merchantRefNumber'])
            ->first();

        if (empty($transaction)) {
            return $this->jsonError('Order not found.', 404);
        }

        $result = $driver->applyPaymentResult($transaction->fresh(), $payload, $businessId);

        if ($result->status === PaymentResult::STATUS_INVALID) {
            $statusPayload = $driver->fetchStatus((string) $payload['merchantRefNumber'], $config);
            if (is_array($statusPayload)) {
                $result = $driver->applyPaymentResult($transaction->fresh(), $statusPayload, $businessId);
            }
        }

        return $this->jsonSuccess([
            'payment_status' => $result->status,
            'message' => $result->message,
            'order' => $this->checkoutService->formatOrderResponse($transaction->fresh()),
            'reference_number' => $result->referenceNumber,
            'fawry_ref_number' => $result->fawryRefNumber,
            'payment_method' => $result->paymentMethod,
            'expiration_time' => $result->expirationTime,
        ]);
    }

    public function session(Request $request, string $provider)
    {
        $businessId = $this->businessId($request);
        $data = $request->validate([
            'storefront_order_id' => 'required|string|max:191',
            'locale' => 'nullable|in:en,ar',
        ]);

        $config = $this->gateways->gatewayConfig($businessId);
        if (($config['provider'] ?? '') !== $provider || empty($config['enabled'])) {
            return $this->jsonError('Online payments are not available.', 422);
        }

        $transaction = Transaction::where('business_id', $businessId)
            ->where('storefront_order_id', $data['storefront_order_id'])
            ->first();

        if (empty($transaction)) {
            return $this->jsonError('Order not found.', 404);
        }

        if (strtolower(trim((string) $transaction->payment_status)) === 'paid') {
            return $this->jsonSuccess([
                'already_paid' => true,
                'order' => $this->checkoutService->formatOrderResponse($transaction),
            ]);
        }

        $driver = $this->gateways->driver($provider);
        $locale = $data['locale'] ?? 'en';
        $returnUrl = $this->buildReturnUrl($locale, $data['storefront_order_id']);
        $session = $driver->buildChargeSession($transaction, $config, $returnUrl, $locale);

        return $this->jsonSuccess($session);
    }

    private function buildReturnUrl(string $locale, string $storefrontOrderId): string
    {
        $base = rtrim((string) config('storefront.url'), '/');
        $lang = $locale === 'ar' ? 'ar' : 'en';

        return $base.'/'.$lang.'/checkout/payment/return/?order='.urlencode($storefrontOrderId);
    }
}
