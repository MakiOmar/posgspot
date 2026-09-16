<?php

namespace App\Services\Storefront\Payment;

use App\Services\Storefront\StorefrontSettingService;
use App\Transaction;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class GeideaPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        private GeideaApiClient $api,
        private GeideaSignature $signature,
        private StorefrontPaymentRecorder $paymentRecorder,
        private StorefrontSettingService $storefrontSettings,
    ) {
    }

    public function name(): string
    {
        return 'geidea';
    }

    public function isConfigured(array $gatewayConfig): bool
    {
        $resolved = $this->resolveCredentials($gatewayConfig);

        return $resolved['public_key'] !== '' && $resolved['api_password'] !== '';
    }

    public function configForTransaction(Transaction $transaction, array $gatewayConfig): array
    {
        $meta = $this->paymentMeta($transaction);
        $mode = $meta['mode'] ?? null;
        if (in_array($mode, ['test', 'live'], true)) {
            $gatewayConfig['geidea']['mode'] = $mode;
        }

        return $gatewayConfig;
    }

    public function extractMerchantReference(array $payload): ?string
    {
        $order = is_array($payload['order'] ?? null) ? $payload['order'] : [];
        $ref = $order['merchantReferenceId']
            ?? $payload['merchantReferenceId']
            ?? $payload['merchantRefNumber']
            ?? $payload['storefront_order_id']
            ?? null;
        if (is_array($payload['order'] ?? null) === false && is_string($payload['order'] ?? null)) {
            $ref = $ref ?? $payload['order'];
        }
        $ref = is_string($ref) || is_numeric($ref) ? trim((string) $ref) : '';

        return $ref !== '' ? $ref : null;
    }

    public function buildChargeSession(
        Transaction $transaction,
        array $gatewayConfig,
        string $returnUrl,
        string $locale,
    ): array {
        $resolved = $this->resolveCredentials($gatewayConfig);
        if ($resolved['public_key'] === '' || $resolved['api_password'] === '') {
            throw ValidationException::withMessages(['payment_method' => ['Geidea is not configured.']]);
        }

        $transaction->loadMissing(['sell_lines.product', 'sell_lines.variations', 'contact']);

        $merchantRef = (string) $transaction->storefront_order_id;
        $amount = $this->signature->formatAmount($transaction->final_total);
        $currency = $resolved['currency'];
        $timestamp = $this->signature->timestamp();
        $sessionSignature = $this->signature->session(
            $resolved['public_key'],
            $amount,
            $currency,
            $merchantRef,
            $timestamp,
            $resolved['api_password']
        );

        $callbackUrl = $this->callbackUrl();
        $body = $this->sessionBody(
            $transaction,
            $resolved,
            $merchantRef,
            $amount,
            $currency,
            $timestamp,
            $sessionSignature,
            $callbackUrl,
            $returnUrl,
            $locale
        );

        $response = $this->api->createSession(
            $resolved['region'],
            $resolved['public_key'],
            $resolved['api_password'],
            $body
        );

        $sessionId = $response['session']['id'] ?? null;
        if (! is_string($sessionId) || $sessionId === '') {
            Log::warning('Geidea session creation failed', [
                'merchant_ref' => $merchantRef,
                'response' => $response,
            ]);
            throw ValidationException::withMessages(['payment_method' => ['Could not start Geidea checkout.']]);
        }

        $this->paymentRecorder->storePaymentMeta($transaction, [
            'provider' => 'geidea',
            'mode' => $resolved['mode'],
            'region' => $resolved['region'],
            'session_id' => $sessionId,
            'currency' => $currency,
        ]);

        $regionMeta = $this->api->regionConfig($resolved['region']);

        return [
            'provider' => 'geidea',
            'session_id' => $sessionId,
            'merchant_reference_id' => $merchantRef,
            'sdk_url' => $regionMeta['hpp'],
            'return_url' => $returnUrl,
            'locale' => $locale === 'ar' ? 'ar' : 'en',
            'region' => $regionMeta['slug'],
            'environment' => $resolved['mode'] === 'live' ? 'prod' : 'test',
            'ui_mode' => $resolved['ui_mode'],
            'container_id' => 'geidea-dropin-container',
        ];
    }

    public function verifyReturnPayload(array $payload, array $gatewayConfig): bool
    {
        $callbackSignature = $payload['signature'] ?? null;
        if ($callbackSignature === null || $callbackSignature === '') {
            // Browser redirect has no HMAC; fulfilment is confirmed via fetchStatus.
            return true;
        }

        return $this->verifyWebhookPayload($payload, $gatewayConfig);
    }

    public function verifyWebhookPayload(array $payload, array $gatewayConfig): bool
    {
        $resolved = $this->resolveCredentials($gatewayConfig);
        if ($resolved['api_password'] === '' || $resolved['public_key'] === '') {
            return false;
        }

        $order = is_array($payload['order'] ?? null) ? $payload['order'] : [];
        $callbackSignature = (string) ($payload['signature'] ?? '');
        if ($callbackSignature === '') {
            return false;
        }

        $expected = $this->signature->callback(
            $resolved['public_key'],
            $order['amount'] ?? null,
            (string) ($order['currency'] ?? ''),
            (string) ($order['orderId'] ?? ''),
            (string) ($order['status'] ?? ''),
            (string) ($order['merchantReferenceId'] ?? ''),
            (string) ($payload['timeStamp'] ?? $payload['timestamp'] ?? ''),
            $resolved['api_password']
        );

        return hash_equals($expected, $callbackSignature);
    }

    public function fetchStatus(string $merchantRef, array $gatewayConfig): ?array
    {
        $resolved = $this->resolveCredentials($gatewayConfig);
        if ($resolved['public_key'] === '' || $resolved['api_password'] === '') {
            return null;
        }

        $remote = $this->api->fetchOrderByMerchantReference(
            $resolved['region'],
            $resolved['public_key'],
            $resolved['api_password'],
            $merchantRef
        );

        if (! is_array($remote) || empty($remote['order']) || ! is_array($remote['order'])) {
            return null;
        }

        return $remote;
    }

    public function applyPaymentResult(Transaction $transaction, array $payload, int $businessId): PaymentResult
    {
        $order = is_array($payload['order'] ?? null) ? $payload['order'] : [];
        if ($order === []) {
            return new PaymentResult(PaymentResult::STATUS_INVALID, 'Missing order payload.');
        }

        $expectedAmount = round((float) $transaction->final_total, 2);
        $callbackAmount = round((float) ($order['amount'] ?? 0), 2);
        if (abs($callbackAmount - $expectedAmount) > 0.01) {
            return new PaymentResult(PaymentResult::STATUS_INVALID, 'Amount mismatch.');
        }

        $meta = $this->paymentMeta($transaction);
        $expectedCurrency = strtoupper((string) ($meta['currency'] ?? 'EGP'));
        $callbackCurrency = strtoupper((string) ($order['currency'] ?? ''));
        if ($callbackCurrency !== '' && $callbackCurrency !== $expectedCurrency) {
            return new PaymentResult(PaymentResult::STATUS_INVALID, 'Currency mismatch.');
        }

        $status = mb_strtolower((string) ($order['status'] ?? ''));
        $orderId = (string) ($order['orderId'] ?? '');

        $resultMeta = [
            'provider' => 'geidea',
            'geidea_order_id' => $orderId !== '' ? $orderId : ($meta['geidea_order_id'] ?? null),
            'order_status' => $order['status'] ?? null,
            'detailed_status' => $order['detailedStatus'] ?? null,
        ];

        if ($status === 'failed') {
            $this->paymentRecorder->storePaymentMeta($transaction, $resultMeta);

            return new PaymentResult(PaymentResult::STATUS_FAILED, 'Payment failed.');
        }

        if ($status !== 'success') {
            return new PaymentResult(PaymentResult::STATUS_FAILED, 'Unrecognized payment status.');
        }

        $detailed = $this->remoteDetailedStatus($transaction, $orderId, $businessId);
        if ($detailed !== 'paid') {
            return new PaymentResult(
                PaymentResult::STATUS_INVALID,
                'Remote order is not paid.'
            );
        }

        $this->paymentRecorder->markPaid($transaction, $businessId, $resultMeta);

        return new PaymentResult(
            PaymentResult::STATUS_PAID,
            'Payment completed.',
            $orderId !== '' ? $orderId : null,
            $orderId !== '' ? $orderId : null,
        );
    }

    public function webhookResponse(PaymentResult $result): Response
    {
        if ($result->status === PaymentResult::STATUS_INVALID) {
            return response()->json(['message' => $result->message ?? 'Invalid callback'], 400);
        }

        return response()->json(['message' => $result->message ?? 'Accepted'], 200);
    }

    /**
     * @param  array<string, mixed>  $gatewayConfig
     * @return array{
     *   mode: string,
     *   region: string,
     *   public_key: string,
     *   api_password: string,
     *   currency: string,
     *   ui_mode: string,
     *   hpp_profile: string,
     *   logo_url: string,
     *   header_color: string,
     *   hide_geidea_logo: bool,
     *   show_email: bool,
     *   show_phone: bool,
     *   show_address: bool,
     *   receipt_page: bool,
     *   language: string
     * }
     */
    public function resolveCredentials(array $gatewayConfig): array
    {
        $geidea = is_array($gatewayConfig['geidea'] ?? null) ? $gatewayConfig['geidea'] : [];
        $mode = ($geidea['mode'] ?? 'test') === 'live' ? 'live' : 'test';
        $prefix = $mode === 'live' ? 'live' : 'test';

        return [
            'mode' => $mode,
            'region' => $this->api->normalizeRegion($geidea['region'] ?? GeideaApiClient::REGION_EGYPT),
            'public_key' => trim((string) ($geidea[$prefix.'_public_key'] ?? '')),
            'api_password' => $this->decryptSecret($geidea[$prefix.'_api_password'] ?? null),
            'currency' => strtoupper(trim((string) ($geidea['currency'] ?? 'EGP'))) ?: 'EGP',
            'ui_mode' => ($geidea['ui_mode'] ?? 'modal') === 'dropin' ? 'dropin' : 'modal',
            'hpp_profile' => ($geidea['hpp_profile'] ?? 'simple') === 'compressed' ? 'compressed' : 'simple',
            'logo_url' => trim((string) ($geidea['logo_url'] ?? '')),
            'header_color' => trim((string) ($geidea['header_color'] ?? '')),
            'hide_geidea_logo' => ! empty($geidea['hide_geidea_logo']),
            'show_email' => ! empty($geidea['show_email']),
            'show_phone' => ! empty($geidea['show_phone']),
            'show_address' => ! empty($geidea['show_address']),
            'receipt_page' => ! empty($geidea['receipt_page']),
            'language' => ($geidea['language'] ?? 'en') === 'ar' ? 'ar' : 'en',
        ];
    }

    /**
     * Confirm detailedStatus from Geidea (unhashed on the callback).
     */
    private function remoteDetailedStatus(Transaction $transaction, string $orderId, int $businessId): string
    {
        $gatewayConfig = $this->configForTransaction(
            $transaction,
            $this->storefrontSettings->get($businessId)['gateway'] ?? []
        );
        $resolved = $this->resolveCredentials($gatewayConfig);

        $remote = null;
        if ($orderId !== '') {
            $remote = $this->api->fetchOrder(
                $resolved['region'],
                $resolved['public_key'],
                $resolved['api_password'],
                $orderId
            );
        }
        if (! is_array($remote) || empty($remote['order'])) {
            $remote = $this->api->fetchOrderByMerchantReference(
                $resolved['region'],
                $resolved['public_key'],
                $resolved['api_password'],
                (string) $transaction->storefront_order_id
            );
        }

        $detailed = mb_strtolower((string) ($remote['order']['detailedStatus'] ?? ''));

        return $detailed;
    }

    /**
     * @param  array<string, mixed>  $resolved
     * @return array<string, mixed>
     */
    private function sessionBody(
        Transaction $transaction,
        array $resolved,
        string $merchantRef,
        string $amount,
        string $currency,
        string $timestamp,
        string $sessionSignature,
        string $callbackUrl,
        string $returnUrl,
        string $locale,
    ): array {
        $contact = $transaction->contact;
        $phone = (string) ($contact->mobile ?? '');
        if ($phone !== '' && ! str_starts_with($phone, '+')) {
            $phone = '+'.$phone;
        }

        $language = $locale === 'ar' ? 'ar' : $resolved['language'];
        $logoUrl = $resolved['logo_url'];
        if ($logoUrl !== '' && ! str_starts_with($logoUrl, 'https://')) {
            $logoUrl = '';
        }

        $customer = [
            'create' => false,
            'setDefaultMethod' => false,
            'email' => (string) ($contact->email ?? ''),
            'phoneNumber' => $phone !== '' ? $phone : null,
            'firstName' => (string) ($contact->first_name ?? $contact->name ?? 'Customer'),
            'lastName' => (string) ($contact->last_name ?? ''),
        ];

        $geideaAddress = $this->customerAddressForSession($transaction);
        if ($geideaAddress !== null) {
            $customer['address'] = $geideaAddress;
        }

        return [
            'merchantPublicKey' => $resolved['public_key'],
            'apiPassword' => $resolved['api_password'],
            'callbackUrl' => $callbackUrl,
            'returnUrl' => $returnUrl,
            'amount' => $amount,
            'currency' => $currency,
            'merchantReferenceId' => $merchantRef,
            'initiatedBy' => 'Internet',
            'language' => $language,
            'timestamp' => $timestamp,
            'paymentOperation' => 'Pay',
            'signature' => $sessionSignature,
            'customer' => $customer,
            'appearance' => [
                'showAddress' => $resolved['show_address'],
                'showEmail' => $resolved['show_email'],
                'showPhone' => $resolved['show_phone'],
                'receiptPage' => $resolved['receipt_page'],
                'merchant' => [
                    'logoUrl' => $logoUrl !== '' ? $logoUrl : null,
                ],
                'styles' => [
                    'headerColor' => $resolved['header_color'] !== '' ? $resolved['header_color'] : null,
                    'hppProfile' => $resolved['hpp_profile'],
                    'hideGeideaLogo' => $resolved['hide_geidea_logo'],
                ],
                'uiMode' => $resolved['ui_mode'],
            ],
            'order' => [
                'integrationType' => 'api',
                'items' => $this->orderItems($transaction),
            ],
            'platform' => [
                'name' => 'Laravel',
                'pluginVersion' => 'storefront-geidea',
            ],
        ];
    }

    /**
     * Map checkout order_addresses into Geidea Create Session customer.address
     * so HPP can prefill Street / City (and related fields).
     *
     * @return array{billing: array<string, string>, shipping: array<string, string>}|null
     */
    private function customerAddressForSession(Transaction $transaction): ?array
    {
        $addresses = ! empty($transaction->order_addresses)
            ? json_decode((string) $transaction->order_addresses, true)
            : [];

        if (! is_array($addresses)) {
            $addresses = [];
        }

        $shippingRaw = is_array($addresses['shipping_address'] ?? null)
            ? $addresses['shipping_address']
            : [];
        $billingRaw = is_array($addresses['billing_address'] ?? null)
            ? $addresses['billing_address']
            : [];

        $shipping = $this->mapStorefrontAddressToGeidea($shippingRaw);
        $billing = $this->mapStorefrontAddressToGeidea($billingRaw !== [] ? $billingRaw : $shippingRaw);

        if ($shipping === null && $billing === null) {
            return null;
        }

        // Prefer whichever side we have; mirror so HPP "same as billing" stays consistent.
        $shipping = $shipping ?? $billing;
        $billing = $billing ?? $shipping;

        return [
            'billing' => $billing,
            'shipping' => $shipping,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, string>|null
     */
    private function mapStorefrontAddressToGeidea(array $raw): ?array
    {
        $line1 = trim((string) ($raw['address_line_1'] ?? $raw['shipping_address_line_1'] ?? ''));
        $line2 = trim((string) ($raw['address_line_2'] ?? $raw['shipping_address_line_2'] ?? ''));
        $city = trim((string) ($raw['city'] ?? $raw['shipping_city'] ?? ''));
        $country = trim((string) ($raw['country'] ?? $raw['shipping_country'] ?? ''));
        $postal = trim((string) ($raw['zip_code'] ?? $raw['shipping_zip_code'] ?? ''));

        if ($this->isPlaceholderStreet($line1)) {
            $line1 = '';
        }

        $street = trim($line1.($line2 !== '' ? ' '.$line2 : ''));
        if ($street === '' && $city === '') {
            return null;
        }

        $mapped = [];
        if ($street !== '') {
            $mapped['street'] = $street;
        }
        if ($city !== '') {
            $mapped['city'] = $city;
        }

        $iso3 = $this->toGeideaCountryCode($country);
        if ($iso3 !== '') {
            $mapped['country'] = $iso3;
        }
        if ($postal !== '') {
            $mapped['postalCode'] = $postal;
        }

        return $mapped !== [] ? $mapped : null;
    }

    private function isPlaceholderStreet(string $street): bool
    {
        $normalized = mb_strtolower(trim($street));

        return in_array($normalized, ['digital delivery', 'store pickup'], true);
    }

    /**
     * Geidea Create Session expects ISO-3166-1 alpha-3 (e.g. EGY).
     */
    private function toGeideaCountryCode(string $country): string
    {
        $country = strtoupper(trim($country));
        if ($country === '') {
            return '';
        }

        if (strlen($country) === 3) {
            return $country;
        }

        $map = [
            'EG' => 'EGY',
            'EGYPT' => 'EGY',
            'SA' => 'SAU',
            'AE' => 'ARE',
            'KW' => 'KWT',
            'BH' => 'BHR',
            'QA' => 'QAT',
            'OM' => 'OMN',
            'JO' => 'JOR',
            'US' => 'USA',
            'GB' => 'GBR',
        ];

        return $map[$country] ?? $country;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function orderItems(Transaction $transaction): array
    {
        $items = [];
        foreach ($transaction->sell_lines as $line) {
            $name = trim(($line->product->name ?? 'Product').' '.($line->variations->name ?? ''));
            $items[] = [
                'merchantItemId' => (string) ($line->variation_id ?: $line->product_id),
                'name' => $name !== '' ? $name : 'Product',
                'description' => $name !== '' ? $name : 'Product',
                'count' => (int) $line->quantity,
                'price' => $this->signature->formatAmount((float) $line->unit_price_inc_tax),
            ];
        }

        return $items;
    }

    private function callbackUrl(): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $url = $base.'/api/storefront/v1/payments/geidea/webhook';

        return str_replace('http://', 'https://', $url);
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentMeta(Transaction $transaction): array
    {
        $meta = $transaction->storefront_payment_meta;
        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?: [];
        }

        return is_array($meta) ? $meta : [];
    }

    private function decryptSecret(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }
}
