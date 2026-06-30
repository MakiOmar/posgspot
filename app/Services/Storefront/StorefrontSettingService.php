<?php

namespace App\Services\Storefront;

use App\BusinessLocation;
use App\StorefrontSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Reads and persists storefront settings from the database.
 */
class StorefrontSettingService
{
    private const CACHE_KEY = 'storefront_settings_';

    public function defaults(): array
    {
        return [
            'selling_location_ids' => [],
            'default_fulfillment_location_id' => null,
            'cod_enabled' => true,
            'gateway' => [
                'provider' => null,
                'api_key' => null,
                'enabled' => false,
            ],
            'shipping' => [
                'flat_rate' => 0,
                'free_shipping_threshold' => 0,
            ],
            'contact' => [
                'phone' => '',
                'email' => '',
                'whatsapp' => '',
            ],
            'social' => [
                'facebook' => '',
                'instagram' => '',
                'tiktok' => '',
                'youtube' => '',
            ],
            'announcement' => [
                'message' => '',
                'link' => '',
                'enabled' => false,
            ],
            'maintenance_mode' => false,
        ];
    }

    public function get(int $businessId): array
    {
        return Cache::remember(self::CACHE_KEY.$businessId, 300, function () use ($businessId) {
            $row = StorefrontSetting::where('business_id', $businessId)->first();

            if (empty($row) || empty($row->value)) {
                return $this->defaults();
            }

            return array_replace_recursive($this->defaults(), $row->value);
        });
    }

    public function save(int $businessId, array $settings): StorefrontSetting
    {
        $merged = array_replace_recursive($this->defaults(), $settings);

        if (! empty($settings['gateway']['api_key'])) {
            $merged['gateway']['api_key'] = Crypt::encryptString($settings['gateway']['api_key']);
        } else {
            $existing = $this->getRaw($businessId);
            $merged['gateway']['api_key'] = $existing['gateway']['api_key'] ?? null;
        }

        $row = StorefrontSetting::updateOrCreate(
            ['business_id' => $businessId],
            ['value' => $merged]
        );

        $this->syncSellingLocations($businessId, $merged['selling_location_ids'] ?? []);
        Cache::forget(self::CACHE_KEY.$businessId);

        return $row;
    }

    public function getSellingLocationIds(int $businessId): array
    {
        $ids = $this->get($businessId)['selling_location_ids'] ?? [];

        return array_values(array_filter(array_map('intval', (array) $ids)));
    }

    public function decryptGatewayApiKey(array $settings): ?string
    {
        $key = $settings['gateway']['api_key'] ?? null;
        if (empty($key)) {
            return null;
        }

        try {
            return Crypt::decryptString($key);
        } catch (\Throwable) {
            return $key;
        }
    }

    private function getRaw(int $businessId): array
    {
        $row = StorefrontSetting::where('business_id', $businessId)->first();

        return empty($row) ? $this->defaults() : array_replace_recursive($this->defaults(), $row->value ?? []);
    }

    /**
     * Sync sells_online flags on business_locations from the settings selection.
     */
    private function syncSellingLocations(int $businessId, array $locationIds): void
    {
        $locationIds = array_map('intval', $locationIds);

        BusinessLocation::where('business_id', $businessId)
            ->update(['sells_online' => false]);

        if (! empty($locationIds)) {
            BusinessLocation::where('business_id', $businessId)
                ->whereIn('id', $locationIds)
                ->update(['sells_online' => true]);
        }
    }
}
