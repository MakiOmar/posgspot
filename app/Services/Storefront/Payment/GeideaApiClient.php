<?php

namespace App\Services\Storefront\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Regional Geidea HTTP client. Mode never changes the host — only credentials do.
 */
class GeideaApiClient
{
    public const REGION_EGYPT = 'EGY-PROD';

    public const REGION_KSA = 'KSA-PROD';

    public const REGION_UAE = 'UAE-PROD';

    /**
     * @return array<string, array{api: string, hpp: string, slug: string}>
     */
    public static function regions(): array
    {
        return [
            self::REGION_EGYPT => [
                'api' => 'https://api.merchant.geidea.net',
                'hpp' => 'https://www.merchant.geidea.net/hpp/geideaCheckout.min.js',
                'slug' => 'egypt',
            ],
            self::REGION_KSA => [
                'api' => 'https://api.ksamerchant.geidea.net',
                'hpp' => 'https://www.ksamerchant.geidea.net/hpp/geideaCheckout.min.js',
                'slug' => 'ksa',
            ],
            self::REGION_UAE => [
                'api' => 'https://api.geidea.ae',
                'hpp' => 'https://payments.geidea.ae/hpp/geideaCheckout.min.js',
                'slug' => 'uae',
            ],
        ];
    }

    public function normalizeRegion(?string $region): string
    {
        $region = strtoupper(trim((string) $region));

        return array_key_exists($region, self::regions()) ? $region : self::REGION_EGYPT;
    }

    /**
     * @return array{api: string, hpp: string, slug: string}
     */
    public function regionConfig(string $region): array
    {
        $regions = self::regions();

        return $regions[$this->normalizeRegion($region)];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function createSession(string $region, string $publicKey, string $apiPassword, array $body): array
    {
        return $this->request(
            $region,
            $publicKey,
            $apiPassword,
            'POST',
            '/payment-intent/api/v2/direct/session',
            $body
        ) ?? [];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function refund(string $region, string $publicKey, string $apiPassword, array $body): array
    {
        return $this->request(
            $region,
            $publicKey,
            $apiPassword,
            'POST',
            '/pgw/api/v1/direct/refund',
            $body
        ) ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function merchantConfig(string $region, string $publicKey, string $apiPassword): ?array
    {
        return $this->request($region, $publicKey, $apiPassword, 'GET', '/pgw/api/v1/config');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchOrder(string $region, string $publicKey, string $apiPassword, string $orderId): ?array
    {
        $orderId = trim($orderId);
        if ($orderId === '') {
            return null;
        }

        return $this->request(
            $region,
            $publicKey,
            $apiPassword,
            'GET',
            '/pgw/api/v1/direct/order/'.rawurlencode($orderId)
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchOrderByMerchantReference(
        string $region,
        string $publicKey,
        string $apiPassword,
        string $merchantReferenceId,
    ): ?array {
        $merchantReferenceId = trim($merchantReferenceId);
        if ($merchantReferenceId === '') {
            return null;
        }

        return $this->request(
            $region,
            $publicKey,
            $apiPassword,
            'GET',
            '/pgw/api/v1/direct/order/merchant/'.rawurlencode($merchantReferenceId)
        );
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>|null
     */
    private function request(
        string $region,
        string $publicKey,
        string $apiPassword,
        string $method,
        string $path,
        ?array $body = null,
    ): ?array {
        $base = $this->regionConfig($region)['api'];
        $url = $base.$path;

        try {
            $pending = Http::timeout(30)
                ->acceptJson()
                ->withBasicAuth($publicKey, $apiPassword);

            if ($method === 'GET') {
                $response = $pending->get($url);
            } else {
                $response = $pending->asJson()->post($url, $body ?? []);
            }

            if (! $response->successful()) {
                Log::warning('Geidea API request failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return $response->json();
            }

            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (\Throwable $e) {
            Log::warning('Geidea API exception', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
