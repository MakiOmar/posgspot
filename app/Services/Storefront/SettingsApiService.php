<?php

namespace App\Services\Storefront;

use App\BusinessLocation;
use App\Services\Storefront\Newsletter\NewsletterProviderManager;
use App\Support\StorefrontLocale;
use App\Utils\BusinessUtil;

/**
 * Public storefront settings exposed via the API.
 */
class SettingsApiService
{
    public function __construct(
        private StorefrontSettingService $storefrontSettings,
        private BusinessUtil $businessUtil,
        private StorefrontContentPresenter $presenter
    ) {
    }

    public function getPublicSettings(int $businessId, string $locale = StorefrontLocale::DEFAULT): array
    {
        $business = $this->businessUtil->getDetails($businessId);
        $settings = $this->storefrontSettings->get($businessId);

        $announcement = $settings['announcement'] ?? [];
        $saleBadge = $settings['sale_badge'] ?? [];
        $rewardName = $settings['reward_points']['name'] ?? null;
        if (empty($rewardName) || is_string($rewardName)) {
            $rewardName = [
                'en' => is_string($rewardName) ? $rewardName : ($business->rp_name ?? 'Reward Points'),
                'ar' => '',
            ];
        }

        return [
            'business_name' => $business->name ?? '',
            'logo_url' => business_logo_url($business) ?: null,
            'currency' => [
                'code' => $business->currency_code ?? 'EGP',
                'symbol' => $business->currency_symbol ?? 'L.E.',
                'precision' => (int) ($business->currency_precision ?? 2),
                'symbol_placement' => $business->currency_symbol_placement ?? 'before',
            ],
            'contact' => $this->formatPublicContact($settings['contact'] ?? []),
            'social' => $settings['social'] ?? [],
            'announcement' => [
                'message' => $this->presenter->localizedSetting($announcement['message'] ?? '', $locale),
                'link' => $announcement['link'] ?? '',
                'enabled' => (bool) ($announcement['enabled'] ?? false),
            ],
            'theme' => [
                'accent_color' => $settings['theme']['accent_color'] ?? '#00d4aa',
            ],
            'sale_badge' => [
                'mode' => $saleBadge['mode'] ?? 'percent',
                'text' => $this->presenter->localizedSetting($saleBadge['text'] ?? 'Sale', $locale, 'Sale'),
            ],
            'catalog' => [
                'show_availability_on_cards' => (bool) ($settings['catalog']['show_availability_on_cards'] ?? true),
            ],
            'cod_enabled' => (bool) ($settings['cod_enabled'] ?? false),
            'maintenance_mode' => (bool) ($settings['maintenance_mode'] ?? false),
            'online_payments' => $this->onlinePaymentsPayload($settings),
            // Public flag only — never expose API key.
            'couriers' => [
                'bosta' => [
                    'enabled' => ! empty($settings['couriers']['bosta']['enabled'])
                        && ! empty($settings['couriers']['bosta']['api_key']),
                ],
            ],
            'reward_points' => [
                'enabled' => (int) ($business->enable_rp ?? 0) === 1,
                'name' => $this->presenter->localizedSetting($rewardName, $locale, $business->rp_name ?? 'Reward Points'),
            ],
            'turnstile' => $this->turnstilePayload($settings),
            'promo_codes' => [
                'enabled_at_checkout' => (bool) ($settings['promo_codes']['enabled_at_checkout'] ?? true),
                'allow_stacking' => (bool) ($settings['promo_codes']['allow_stacking'] ?? false),
            ],
            'payment_icons' => $this->paymentIconsPayload($settings),
            'banners' => $this->bannersPayload($settings, $locale),
            'newsletter' => [
                'enabled' => app(NewsletterProviderManager::class)->isEnabled($businessId),
            ],
            'repair' => $this->repairPayload($businessId),
            'locales' => ['en', 'ar'],
        ];
    }

    public function getLocations(int $businessId): array
    {
        $locationIds = $this->storefrontSettings->getSellingLocationIds($businessId);
        if (empty($locationIds)) {
            return [];
        }

        return BusinessLocation::where('business_id', $businessId)
            ->whereIn('id', $locationIds)
            ->where('is_active', 1)
            ->get()
            ->map(fn ($loc) => $this->formatLocation($loc))
            ->values()
            ->all();
    }

    /**
     * Public contact block: never expose a raw email (harvesting / mailto in SSR).
     * The storefront decodes email_encoded client-side only.
     */
    public function formatPublicContact(array $contact): array
    {
        return [
            'phone' => $contact['phone'] ?? null,
            'whatsapp' => $contact['whatsapp'] ?? null,
            'email_encoded' => $this->encodePublicEmail($contact['email'] ?? null),
        ];
    }

    public function formatLocation(BusinessLocation $loc): array
    {
        return [
            'id' => $loc->id,
            'name' => $loc->name,
            'address' => $this->resolveDisplayAddress($loc),
            'phone' => $loc->mobile,
            'email_encoded' => $this->encodePublicEmail($loc->email),
            'enable_pickup' => (bool) ($loc->enable_pickup ?? false),
            'latitude' => $loc->latitude,
            'longitude' => $loc->longitude,
            'maps_url' => $this->mapsUrl($loc),
        ];
    }

    /**
     * Public storefront address: optional override, else composed landmark/city fields.
     */
    public function resolveDisplayAddress(BusinessLocation $loc): string
    {
        $override = trim((string) ($loc->storefront_address ?? ''));
        if ($override !== '') {
            return $override;
        }

        return $this->composeAddress($loc);
    }

    /**
     * Base64-encode an email for public API responses (decoded client-side only).
     */
    private function encodePublicEmail(?string $email): ?string
    {
        $email = trim((string) $email);

        return $email !== '' ? base64_encode($email) : null;
    }

    public function composeAddress(BusinessLocation $loc): string
    {
        $parts = array_filter([
            $loc->landmark,
            $loc->city,
            $loc->state,
            $loc->country,
            $loc->zip_code,
        ]);

        return implode(', ', $parts);
    }

    public function mapsUrl(BusinessLocation $loc): ?string
    {
        if (! empty($loc->latitude) && ! empty($loc->longitude)) {
            return 'https://www.google.com/maps?q='.$loc->latitude.','.$loc->longitude;
        }

        $address = $this->resolveDisplayAddress($loc);
        if ($address === '') {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query='.urlencode($address);
    }

    private function onlinePaymentsPayload(array $settings): array
    {
        $gateway = $settings['gateway'] ?? [];
        $provider = $gateway['provider'] ?? null;
        $enabled = ! empty($gateway['enabled']) && ! empty($provider);

        return [
            'enabled' => $enabled,
            'provider' => $enabled ? (string) $provider : null,
            'label' => $enabled ? (string) (config("storefront-payments.labels.{$provider}") ?? ucfirst((string) $provider)) : null,
        ];
    }

    private function turnstilePayload(array $settings): array
    {
        $siteKey = trim((string) ($settings['turnstile']['site_key'] ?? ''));
        $secret = $this->storefrontSettings->decryptTurnstileSecretKey($settings);
        $enabled = $siteKey !== '' && ! empty($secret);

        return [
            'enabled' => $enabled,
            'site_key' => $enabled ? $siteKey : null,
        ];
    }

    /**
     * Public footer payment icons (label + absolute icon URL only).
     *
     * @return array<int, array{label: string, icon_url: string}>
     */
    private function paymentIconsPayload(array $settings): array
    {
        $icons = $settings['payment_icons'] ?? [];
        if (! is_array($icons)) {
            return [];
        }

        $out = [];
        foreach ($icons as $row) {
            if (! is_array($row)) {
                continue;
            }
            $url = $this->storefrontSettings->paymentIconPublicUrl($row);
            if ($url === null) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            $out[] = [
                'label' => $label !== '' ? $label : 'Payment',
                'icon_url' => $url,
            ];
        }

        return $out;
    }

    /**
     * Public promotional banners (enabled rows with a resolvable image).
     *
     * @return array<int, array{id: string, placement: string, category_slug: string|null, title: string, link: string, image_url: string}>
     */
    private function bannersPayload(array $settings, string $locale): array
    {
        $banners = $settings['banners'] ?? [];
        if (! is_array($banners)) {
            return [];
        }

        $out = [];
        foreach ($banners as $row) {
            if (! is_array($row) || empty($row['enabled'])) {
                continue;
            }
            $imageUrl = $this->storefrontSettings->bannerPublicUrl($row);
            if ($imageUrl === null) {
                continue;
            }

            $placement = ($row['placement'] ?? 'home') === 'category' ? 'category' : 'home';
            $categorySlug = trim((string) ($row['category_slug'] ?? ''));
            if ($placement === 'category' && $categorySlug === '') {
                continue;
            }

            $out[] = [
                'id' => (string) ($row['id'] ?? ''),
                'placement' => $placement,
                'category_slug' => $placement === 'category' ? $categorySlug : null,
                'title' => $this->presenter->localizedSetting($row['title'] ?? '', $locale, ''),
                'link' => trim((string) ($row['link'] ?? '')),
                'image_url' => $imageUrl,
            ];
        }

        return $out;
    }

    /**
     * @return array{lookup_enabled: bool, lookup_by_mobile: bool}
     */
    private function repairPayload(int $businessId): array
    {
        $lookup = app(RepairStatusLookupService::class);

        return [
            'lookup_enabled' => $lookup->isAvailable($businessId),
            'lookup_by_mobile' => $lookup->lookupByMobileEnabled(),
        ];
    }
}
