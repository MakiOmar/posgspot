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
                'fawry' => [
                    'merchant_code' => '',
                    'security_key' => null,
                    'staging' => false,
                ],
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
                'message' => [
                    'en' => '',
                    'ar' => '',
                ],
                'link' => '',
                'enabled' => false,
            ],
            'theme' => [
                'accent_color' => '#00d4aa',
            ],
            'sale_badge' => [
                'mode' => 'percent',
                'text' => [
                    'en' => 'Sale',
                    'ar' => 'تخفيض',
                ],
            ],
            'catalog' => [
                'show_availability_on_cards' => true,
            ],
            'maintenance_mode' => false,
            'reward_points' => [
                'name' => [
                    'en' => 'Reward Points',
                    'ar' => 'نقاط المكافآت',
                ],
            ],
            'turnstile' => [
                'site_key' => '',
                'secret_key' => null,
            ],
            'promo_codes' => [
                'enabled_at_checkout' => true,
                'allow_stacking' => false,
            ],
            // Footer payment method icons (label + uploaded file or external URL).
            'payment_icons' => [],
            'newsletter' => [
                'enabled' => false,
                'provider' => null,
                'double_opt_in' => true,
                'mailchimp' => [
                    'api_key' => null,
                    'audience_id' => '',
                ],
                'mailerlite' => [
                    'api_token' => null,
                    'group_id' => '',
                ],
                'aweber' => [
                    'access_token' => null,
                    'account_id' => '',
                    'list_id' => '',
                ],
            ],
        ];
    }

    public function get(int $businessId): array
    {
        return Cache::remember(self::CACHE_KEY.$businessId, 300, function () use ($businessId) {
            $row = StorefrontSetting::where('business_id', $businessId)->first();

            if (empty($row) || empty($row->value)) {
                return $this->defaults();
            }

            return $this->normalizeLocalized(array_replace_recursive($this->defaults(), $row->value));
        });
    }

    /**
     * Migrate legacy single-string fields to locale maps.
     */
    private function normalizeLocalized(array $settings): array
    {
        if (isset($settings['announcement']['message']) && is_string($settings['announcement']['message'])) {
            $settings['announcement']['message'] = [
                'en' => $settings['announcement']['message'],
                'ar' => '',
            ];
        }

        if (isset($settings['sale_badge']['text']) && is_string($settings['sale_badge']['text'])) {
            $settings['sale_badge']['text'] = [
                'en' => $settings['sale_badge']['text'],
                'ar' => '',
            ];
        }

        if (isset($settings['reward_points']['name']) && is_string($settings['reward_points']['name'])) {
            $settings['reward_points']['name'] = [
                'en' => $settings['reward_points']['name'],
                'ar' => '',
            ];
        }

        return $settings;
    }

    public function save(int $businessId, array $settings): StorefrontSetting
    {
        $merged = array_replace_recursive($this->defaults(), $settings);

        // Numeric lists must replace wholesale — array_replace_recursive cannot clear rows.
        if (array_key_exists('payment_icons', $settings)) {
            $merged['payment_icons'] = $this->normalizePaymentIcons($settings['payment_icons']);
        }

        if (! empty($settings['gateway']['api_key'])) {
            $merged['gateway']['api_key'] = Crypt::encryptString($settings['gateway']['api_key']);
        } else {
            $existing = $this->getRaw($businessId);
            $merged['gateway']['api_key'] = $existing['gateway']['api_key'] ?? null;
        }

        if (! empty($settings['gateway']['fawry']['security_key'])) {
            $merged['gateway']['fawry']['security_key'] = Crypt::encryptString($settings['gateway']['fawry']['security_key']);
        } else {
            $existing = $existing ?? $this->getRaw($businessId);
            $merged['gateway']['fawry']['security_key'] = $existing['gateway']['fawry']['security_key'] ?? null;
        }

        if (! empty($settings['turnstile']['secret_key'])) {
            $merged['turnstile']['secret_key'] = Crypt::encryptString($settings['turnstile']['secret_key']);
        } else {
            $existing = $existing ?? $this->getRaw($businessId);
            $merged['turnstile']['secret_key'] = $existing['turnstile']['secret_key'] ?? null;
        }

        $existing = $existing ?? $this->getRaw($businessId);
        $merged['newsletter'] = $this->mergeNewsletterSecrets(
            $merged['newsletter'] ?? [],
            $settings['newsletter'] ?? [],
            $existing['newsletter'] ?? []
        );

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

    public function decryptFawrySecurityKey(array $settings): ?string
    {
        $key = $settings['gateway']['fawry']['security_key'] ?? null;
        if (empty($key)) {
            return $this->decryptGatewayApiKey($settings);
        }

        try {
            return Crypt::decryptString($key);
        } catch (\Throwable) {
            return $key;
        }
    }

    public function decryptTurnstileSecretKey(array $settings): ?string
    {
        $key = $settings['turnstile']['secret_key'] ?? null;
        if (empty($key)) {
            return null;
        }

        try {
            return Crypt::decryptString($key);
        } catch (\Throwable) {
            return $key;
        }
    }

    /**
     * Return newsletter settings with provider secrets decrypted (for outbound API calls).
     *
     * @param  array<string, mixed>  $newsletter
     * @return array<string, mixed>
     */
    public function withDecryptedNewsletterSecrets(array $newsletter): array
    {
        foreach (['mailchimp' => 'api_key', 'mailerlite' => 'api_token', 'aweber' => 'access_token'] as $provider => $secretField) {
            $raw = $newsletter[$provider][$secretField] ?? null;
            if (empty($raw)) {
                $newsletter[$provider][$secretField] = null;
                continue;
            }
            try {
                $newsletter[$provider][$secretField] = Crypt::decryptString($raw);
            } catch (\Throwable) {
                $newsletter[$provider][$secretField] = $raw;
            }
        }

        return $newsletter;
    }

    /**
     * Encrypt new newsletter secrets; keep existing when form fields are blank.
     *
     * @param  array<string, mixed>  $merged
     * @param  array<string, mixed>  $incoming
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private function mergeNewsletterSecrets(array $merged, array $incoming, array $existing): array
    {
        $secretPaths = [
            ['mailchimp', 'api_key'],
            ['mailerlite', 'api_token'],
            ['aweber', 'access_token'],
        ];

        foreach ($secretPaths as [$provider, $field]) {
            $newValue = $incoming[$provider][$field] ?? null;
            if (! empty($newValue)) {
                $merged[$provider][$field] = Crypt::encryptString((string) $newValue);
            } else {
                $merged[$provider][$field] = $existing[$provider][$field] ?? null;
            }
        }

        return $merged;
    }

    private function getRaw(int $businessId): array
    {
        $row = StorefrontSetting::where('business_id', $businessId)->first();

        return empty($row) ? $this->defaults() : array_replace_recursive($this->defaults(), $row->value ?? []);
    }

    /**
     * Normalize payment icon rows for persistence.
     * Each row: label + either uploaded filename (image) or external url.
     *
     * @param  mixed  $icons
     * @return array<int, array{label: string, image: string|null, url: string}>
     */
    public function normalizePaymentIcons($icons): array
    {
        if (! is_array($icons)) {
            return [];
        }

        $normalized = [];
        foreach ($icons as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $image = trim((string) ($row['image'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));

            if ($label === '' && $image === '' && $url === '') {
                continue;
            }

            if ($label === '') {
                $label = $image !== '' ? pathinfo($image, PATHINFO_FILENAME) : 'Payment';
            }

            $normalized[] = [
                'label' => mb_substr($label, 0, 80),
                'image' => $image !== '' ? $image : null,
                'url' => $image === '' ? mb_substr($url, 0, 500) : '',
            ];
        }

        return array_values($normalized);
    }

    /**
     * Absolute public URL for a stored payment icon row.
     */
    public function paymentIconPublicUrl(array $row): ?string
    {
        $image = trim((string) ($row['image'] ?? ''));
        if ($image !== '') {
            return asset('uploads/storefront_payment_icons/'.$image);
        }

        $url = trim((string) ($row['url'] ?? ''));
        if ($url === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        // Allow relative paths under /uploads or site root.
        if (str_starts_with($url, '/')) {
            return url($url);
        }

        return asset($url);
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
