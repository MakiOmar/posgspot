<?php

namespace App\Services\Storefront\Payment;

use App\Services\Storefront\StorefrontSettingService;
use App\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class FawryPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        private StorefrontSettingService $storefrontSettings,
        private StorefrontPaymentRecorder $paymentRecorder,
    ) {
    }

    public function name(): string
    {
        return 'fawry';
    }

    public function buildChargeSession(
        Transaction $transaction,
        array $gatewayConfig,
        string $returnUrl,
        string $locale,
    ): array {
        $config = $this->resolveConfig($gatewayConfig);
        $transaction->loadMissing(['sell_lines.product', 'sell_lines.variations', 'contact']);

        $merchantRefNum = (string) $transaction->storefront_order_id;
        $customerProfileId = (string) ($transaction->contact_id ?? '');
        $chargeItems = $this->buildChargeItems($transaction);
        $sortedSignature = $this->sortedItemsSignature($chargeItems);
        $signature = hash(
            'sha256',
            $config['merchant_code']
            .$merchantRefNum
            .$customerProfileId
            .$returnUrl
            .$sortedSignature
            .$config['security_key']
        );

        $customer = $this->buildCustomerPayload($transaction);

        return [
            'provider' => 'fawry',
            'sdk_url' => $this->sdkUrl($config['staging']),
            'return_url' => $returnUrl,
            'locale' => $locale === 'ar' ? 'ar' : 'en',
            'charge' => [
                'merchantCode' => $config['merchant_code'],
                'merchantRefNum' => $merchantRefNum,
                'customerProfileId' => $customerProfileId,
                'customerMobile' => $customer['customerMobile'],
                'customerEmail' => $customer['customerEmail'],
                'customerName' => $customer['customerName'],
                'chargeItems' => $chargeItems,
                'paymentExpiry' => '',
                'returnUrl' => $returnUrl,
                'signature' => $signature,
            ],
            'customer' => $customer,
        ];
    }

    public function verifyReturnPayload(array $payload, array $gatewayConfig): bool
    {
        $config = $this->resolveConfig($gatewayConfig);
        $refNumber = $payload['referenceNumber'] ?? $payload['fawryRefNumber'] ?? '';
        $messageSignature = $payload['signature'] ?? $payload['messageSignature'] ?? '';

        $signatureString = $refNumber
            .($payload['merchantRefNumber'] ?? '')
            .$this->formatAmount($payload['paymentAmount'] ?? null)
            .$this->formatAmount($payload['orderAmount'] ?? null)
            .($payload['orderStatus'] ?? '')
            .($payload['paymentMethod'] ?? '')
            .(array_key_exists('fawryFees', $payload) ? $this->formatAmount($payload['fawryFees']) : '')
            .(array_key_exists('shippingFees', $payload) ? $this->formatAmount($payload['shippingFees']) : '')
            .($payload['authNumber'] ?? '')
            .($payload['paymentReferenceNumber'] ?? '')
            .$config['security_key'];

        return hash_equals(hash('sha256', $signatureString), (string) $messageSignature);
    }

    public function verifyWebhookPayload(array $payload, array $gatewayConfig): bool
    {
        $config = $this->resolveConfig($gatewayConfig);
        $refNumber = $payload['referenceNumber'] ?? $payload['fawryRefNumber'] ?? '';
        $messageSignature = $payload['signature'] ?? $payload['messageSignature'] ?? '';

        $signatureString = $refNumber
            .($payload['merchantRefNumber'] ?? '')
            .$this->formatAmount($payload['paymentAmount'] ?? null)
            .$this->formatAmount($payload['orderAmount'] ?? null)
            .($payload['orderStatus'] ?? '')
            .($payload['paymentMethod'] ?? '')
            .($payload['paymentRefrenceNumber'] ?? '')
            .$config['security_key'];

        return hash_equals(hash('sha256', $signatureString), (string) $messageSignature);
    }

    public function fetchStatus(string $merchantRef, array $gatewayConfig): ?array
    {
        $config = $this->resolveConfig($gatewayConfig);
        $signature = hash('sha256', $config['merchant_code'].$merchantRef.$config['security_key']);
        $base = $this->apiBase($config['staging']);
        $url = $base.'/ECommerceWeb/Fawry/payments/status/v2';

        try {
            $response = Http::timeout(15)->get($url, [
                'merchantCode' => $config['merchant_code'],
                'merchantRefNumber' => $merchantRef,
                'signature' => $signature,
            ]);

            if (! $response->successful()) {
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::warning('Fawry status API failed', ['merchant_ref' => $merchantRef, 'error' => $e->getMessage()]);

            return null;
        }
    }

    public function applyPaymentResult(Transaction $transaction, array $payload, int $businessId): PaymentResult
    {
        if (! array_key_exists('merchantRefNumber', $payload)) {
            return new PaymentResult(PaymentResult::STATUS_INVALID, 'Missing merchant reference.');
        }

        $orderStatus = strtoupper((string) ($payload['orderStatus'] ?? ''));
        $orderAmount = (float) ($payload['orderAmount'] ?? 0);
        $expected = round((float) $transaction->final_total, 2);

        if (abs($orderAmount - $expected) > 0.01) {
            return new PaymentResult(PaymentResult::STATUS_INVALID, 'Amount mismatch.');
        }

        $meta = [
            'provider' => 'fawry',
            'fawry_ref_number' => $payload['fawryRefNumber'] ?? $payload['referenceNumber'] ?? null,
            'reference_number' => $payload['referenceNumber'] ?? null,
            'payment_method' => $payload['paymentMethod'] ?? null,
            'order_status' => $orderStatus,
        ];

        if ($orderStatus === 'PAID') {
            $this->paymentRecorder->markPaid($transaction, $businessId, $meta);

            return new PaymentResult(
                PaymentResult::STATUS_PAID,
                'Payment completed.',
                $meta['fawry_ref_number'],
                $meta['reference_number'],
                $meta['payment_method'],
            );
        }

        if (in_array($orderStatus, ['NEW', 'UNPAID'], true)) {
            $this->paymentRecorder->storePaymentMeta($transaction, array_merge($meta, [
                'expiration_time' => $payload['expirationTime'] ?? null,
            ]));

            return new PaymentResult(
                PaymentResult::STATUS_PENDING,
                'Awaiting payment at Fawry.',
                $meta['fawry_ref_number'],
                $meta['reference_number'],
                $meta['payment_method'],
                $payload['expirationTime'] ?? null,
            );
        }

        if (in_array($orderStatus, ['CANCELED', 'EXPIRED', 'REFUNDED'], true)) {
            return new PaymentResult(PaymentResult::STATUS_FAILED, "Order status: {$orderStatus}");
        }

        return new PaymentResult(PaymentResult::STATUS_FAILED, 'Unrecognized payment status.');
    }

    public function webhookResponse(PaymentResult $result): Response
    {
        if ($result->status === PaymentResult::STATUS_INVALID) {
            return response()->json(['status' => '300', 'message' => 'INVALID_SIGNATURE'], 300);
        }

        if ($result->isSuccess()) {
            return response()->json(['status' => '200', 'message' => 'SUCCESS'], 200);
        }

        return response()->json(['status' => '202', 'message' => 'FAILED'], 202);
    }

    /**
     * @return array<int, array{itemId: string|int, description: string, quantity: float|int, price: string}>
     */
    public function buildChargeItems(Transaction $transaction): array
    {
        $items = [];

        foreach ($transaction->sell_lines as $line) {
            $name = trim(($line->product->name ?? 'Product').' '.($line->variations->name ?? ''));
            $items[] = [
                'itemId' => (string) ($line->variation_id ?: $line->product_id),
                'description' => $name !== '' ? $name : 'Product',
                'quantity' => (float) $line->quantity,
                'price' => $this->formatAmount((float) $line->unit_price_inc_tax),
            ];
        }

        $shipping = (float) ($transaction->shipping_charges ?? 0);
        if ($shipping > 0) {
            $items[] = [
                'itemId' => 'shipping',
                'description' => 'Shipping',
                'quantity' => 1,
                'price' => $this->formatAmount($shipping),
            ];
        }

        $tax = (float) ($transaction->tax_amount ?? 0);
        if ($tax > 0) {
            $items[] = [
                'itemId' => 'tax',
                'description' => 'Tax',
                'quantity' => 1,
                'price' => $this->formatAmount($tax),
            ];
        }

        $redeemed = (float) ($transaction->rp_redeemed_amount ?? 0);
        $couponDiscount = (float) ($transaction->discount_amount ?? 0);
        $totalDiscount = $redeemed + $couponDiscount;
        if ($totalDiscount > 0) {
            $items[] = [
                'itemId' => 'discount',
                'description' => 'Discount',
                'quantity' => 1,
                'price' => $this->formatAmount($totalDiscount * -1),
            ];
        }

        usort($items, fn ($a, $b) => strcmp((string) $a['itemId'], (string) $b['itemId']));

        return $items;
    }

    /**
     * @param  array<int, array{itemId: string|int, quantity: float|int, price: string}>  $items
     */
    public function sortedItemsSignature(array $items): string
    {
        $signature = '';
        foreach ($items as $item) {
            $signature .= $item['itemId'].$item['quantity'].$item['price'];
        }

        return $signature;
    }

    /**
     * @return array{merchant_code: string, security_key: string, staging: bool}
     */
    public function resolveConfig(array $gatewayConfig): array
    {
        $fawry = $gatewayConfig['fawry'] ?? [];
        $securityKey = $fawry['security_key'] ?? null;

        if (! empty($securityKey)) {
            try {
                $securityKey = \Illuminate\Support\Facades\Crypt::decryptString($securityKey);
            } catch (\Throwable) {
                // Stored as plain text during migration.
            }
        }

        if (empty($securityKey) && ! empty($gatewayConfig['api_key'])) {
            $securityKey = $this->storefrontSettings->decryptGatewayApiKey($gatewayConfig);
        }

        return [
            'merchant_code' => (string) ($fawry['merchant_code'] ?? ''),
            'security_key' => (string) ($securityKey ?? ''),
            'staging' => (bool) ($fawry['staging'] ?? false),
        ];
    }

    private function buildCustomerPayload(Transaction $transaction): array
    {
        $contact = $transaction->contact;

        return [
            'customerName' => trim((string) ($contact->name ?? 'Customer')),
            'customerMobile' => (string) ($contact->mobile ?? ''),
            'customerEmail' => (string) ($contact->email ?? ''),
            'customerId' => (string) ($contact->id ?? ''),
        ];
    }

    private function formatAmount(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        return number_format((float) $amount, 2, '.', '');
    }

    private function sdkUrl(bool $staging): string
    {
        if ($staging) {
            return 'https://atfawry.fawrystaging.com/atfawry/plugin/assets/payments/js/fawrypay-payments.js';
        }

        return 'https://www.atfawry.com/atfawry/plugin/assets/payments/js/fawrypay-payments.js';
    }

    private function apiBase(bool $staging): string
    {
        if ($staging) {
            return 'https://atfawry.fawrystaging.com';
        }

        return 'https://www.atfawry.com';
    }
}
