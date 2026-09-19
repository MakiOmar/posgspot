<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\CheckoutService;
use App\Services\Storefront\Payment\PaymentGatewayManager;
use App\Services\Storefront\Payment\PaymentResult;
use App\Transaction;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Confirms hosted-checkout return payloads after the customer is redirected back.
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
        $merchantRef = $driver->extractMerchantReference($payload);
        if (empty($merchantRef) && ! empty($payload['order']) && is_string($payload['order'])) {
            $merchantRef = $payload['order'];
        }
        if (empty($merchantRef)) {
            return $this->jsonError('Missing merchant reference.', 422);
        }

        $transaction = Transaction::where('business_id', $businessId)
            ->where('storefront_order_id', $merchantRef)
            ->first();

        if (empty($transaction)) {
            return $this->jsonError('Order not found.', 404);
        }

        $accessToken = $this->resolveAccessToken($payload);
        try {
            $this->checkoutService->assertOrderAccess($transaction, $accessToken);
        } catch (ValidationException $e) {
            return $this->jsonError('Invalid order access token.', 403, $e->errors());
        }

        $config = $driver->configForTransaction($transaction, $config);

        if (! $driver->verifyReturnPayload($payload, $config)) {
            return $this->jsonError('Invalid payment signature.', 422);
        }

        $result = $driver->applyPaymentResult($transaction->fresh(), $payload, $businessId);

        if ($result->status === PaymentResult::STATUS_INVALID) {
            $statusPayload = $driver->fetchStatus($merchantRef, $config);
            if (is_array($statusPayload)) {
                $result = $driver->applyPaymentResult($transaction->fresh(), $statusPayload, $businessId);
            }
        }

        // Public return must not expose invoice print URLs — use account / track-order.
        return $this->jsonSuccess([
            'payment_status' => $result->status,
            'message' => $result->message,
            'order' => $this->checkoutService->formatOrderResponse($transaction->fresh(), false),
            'provider_ref_number' => $result->providerRefNumber,
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
            'order_access_token' => 'required|string|max:128',
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

        try {
            $this->checkoutService->assertOrderAccess($transaction, $data['order_access_token']);
        } catch (ValidationException $e) {
            return $this->jsonError('Invalid order access token.', 403, $e->errors());
        }

        if (strtolower(trim((string) $transaction->payment_status)) === 'paid') {
            return $this->jsonSuccess([
                'already_paid' => true,
                'order' => $this->checkoutService->formatOrderResponse($transaction, false),
            ]);
        }

        $driver = $this->gateways->driver($provider);
        $locale = $data['locale'] ?? 'en';
        $returnUrl = $this->buildReturnUrl($locale, $data['storefront_order_id'], $data['order_access_token']);
        $session = $driver->buildChargeSession($transaction, $config, $returnUrl, $locale);

        return $this->jsonSuccess($session);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveAccessToken(array $payload): ?string
    {
        foreach (['order_access_token', 'access'] as $key) {
            if (! empty($payload[$key]) && is_string($payload[$key])) {
                return $payload[$key];
            }
        }

        return null;
    }

    private function buildReturnUrl(string $locale, string $storefrontOrderId, string $orderAccessToken): string
    {
        $base = rtrim((string) config('storefront.url'), '/');
        $lang = $locale === 'ar' ? 'ar' : 'en';

        return $base.'/'.$lang.'/checkout/payment/return/?order='.urlencode($storefrontOrderId)
            .'&access='.urlencode($orderAccessToken);
    }
}
