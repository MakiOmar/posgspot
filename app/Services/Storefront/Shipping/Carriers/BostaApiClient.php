<?php

namespace App\Services\Storefront\Shipping\Carriers;

use App\Services\Storefront\StorefrontSettingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Low-level Bosta HTTP client aligned with the official WooCommerce plugin.
 */
class BostaApiClient
{
    public const EGYPT_COUNTRY_ID = '60e4482c7cb7d4bc4849c4d5';

    public function __construct(private StorefrontSettingService $settings)
    {
    }

    public function isConfigured(int $businessId): bool
    {
        $cfg = $this->config($businessId);

        return ! empty($cfg['enabled']) && ! empty($cfg['api_key']);
    }

    /**
     * @return array{enabled:bool,api_key:?string,staging:bool}
     */
    public function config(int $businessId): array
    {
        $all = $this->settings->get($businessId);
        $bosta = $all['couriers']['bosta'] ?? [];

        return [
            'enabled' => ! empty($bosta['enabled']),
            'api_key' => $bosta['api_key'] ?? null,
            'staging' => ! empty($bosta['staging']),
        ];
    }

    public function apiKey(int $businessId): ?string
    {
        $raw = $this->config($businessId)['api_key'] ?? null;
        if (empty($raw)) {
            return null;
        }
        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable $e) {
            return $raw;
        }
    }

    public function baseUrl(int $businessId): string
    {
        // WooCommerce plugin uses production only; staging is optional here.
        return ! empty($this->config($businessId)['staging'])
            ? 'https://stg-app.bosta.co/api/v2'
            : 'https://app.bosta.co/api/v2';
    }

    /**
     * @return array{success:bool,status:int,body:?array,error:?string}
     */
    public function request(int $businessId, string $method, string $path, ?array $body = null): array
    {
        $apiKey = $this->apiKey($businessId);
        if (! $apiKey) {
            return ['success' => false, 'status' => 0, 'body' => null, 'error' => 'Missing Bosta API key'];
        }

        $url = rtrim($this->baseUrl($businessId), '/').'/'.ltrim($path, '/');
        $headers = [
            'authorization' => $apiKey,
            'Content-Type' => 'application/json',
            'X-Requested-By' => 'GamesSpotPOS',
        ];

        try {
            $pending = Http::withHeaders($headers)->timeout(25)->acceptJson();
            $response = match (strtoupper($method)) {
                'GET' => $pending->get($url),
                'POST' => $pending->post($url, $body ?? []),
                'PUT' => $pending->put($url, $body ?? []),
                'DELETE' => $pending->delete($url, $body ?? []),
                default => throw new \InvalidArgumentException('Unsupported method '.$method),
            };

            $json = $response->json();
            if (! $response->successful()) {
                return [
                    'success' => false,
                    'status' => $response->status(),
                    'body' => is_array($json) ? $json : null,
                    'error' => is_array($json)
                        ? (string) ($json['message'] ?? $json['error'] ?? $response->body())
                        : $response->body(),
                ];
            }

            return [
                'success' => true,
                'status' => $response->status(),
                'body' => is_array($json) ? $json : null,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Bosta API exception: '.$e->getMessage(), ['path' => $path]);

            return ['success' => false, 'status' => 0, 'body' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Cached zoning tree (cities + districts) for Egypt.
     *
     * @return array<int, array<string, mixed>>
     */
    public function zoning(int $businessId): array
    {
        if (! $this->isConfigured($businessId)) {
            return [];
        }

        $staging = ! empty($this->config($businessId)['staging']) ? 'stg' : 'prod';
        $cacheKey = "bosta_zoning_{$businessId}_{$staging}";

        return Cache::remember($cacheKey, now()->addHours(12), function () use ($businessId) {
            $res = $this->request(
                $businessId,
                'GET',
                'cities/getAllDistricts?countryId='.self::EGYPT_COUNTRY_ID
            );
            if (! $res['success']) {
                return [];
            }
            $data = $res['body']['data'] ?? [];

            return is_array($data) ? $data : [];
        });
    }

    public function flushZoningCache(int $businessId): void
    {
        Cache::forget("bosta_zoning_{$businessId}_prod");
        Cache::forget("bosta_zoning_{$businessId}_stg");
    }

    /**
     * @return array{city_code:string,city_name:string,districts:array<int,array{id:string,label:string,zone:?string}>}|null
     */
    public function cityByStateCode(int $businessId, string $stateCode, string $locale = 'en'): ?array
    {
        $stateCode = strtoupper(trim($stateCode));
        if ($stateCode === '') {
            return null;
        }

        foreach ($this->zoning($businessId) as $city) {
            if (! is_array($city)) {
                continue;
            }
            if (empty($city['dropOffAvailability'])) {
                continue;
            }
            $code = strtoupper((string) ($city['cityCode'] ?? ''));
            if ($code !== $stateCode) {
                continue;
            }

            $isAr = $locale === 'ar';
            $cityName = $isAr
                ? (string) ($city['cityOtherName'] ?? $city['cityName'] ?? $code)
                : (string) ($city['cityName'] ?? $city['cityOtherName'] ?? $code);

            $districts = [];
            foreach ($city['districts'] ?? [] as $district) {
                if (! is_array($district) || empty($district['districtId'])) {
                    continue;
                }
                $zone = $isAr
                    ? (string) ($district['zoneOtherName'] ?? $district['zoneName'] ?? '')
                    : (string) ($district['zoneName'] ?? $district['zoneOtherName'] ?? '');
                $name = $isAr
                    ? (string) ($district['districtOtherName'] ?? $district['districtName'] ?? '')
                    : (string) ($district['districtName'] ?? $district['districtOtherName'] ?? '');
                $label = ($zone !== '' && $zone !== $name) ? "{$zone} - {$name}" : $name;
                $districts[] = [
                    'id' => (string) $district['districtId'],
                    'label' => $label,
                    'zone' => $zone !== '' ? $zone : null,
                ];
            }

            return [
                'city_code' => $code,
                'city_name' => $cityName,
                'districts' => $districts,
            ];
        }

        return null;
    }
}
