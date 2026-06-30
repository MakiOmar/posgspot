<?php

namespace App\Services\Storefront;

use App\Business;
use App\BusinessLocation;
use App\Utils\BusinessUtil;

/**
 * Public storefront settings exposed via the API.
 */
class SettingsApiService
{
    public function __construct(
        private StorefrontSettingService $storefrontSettings,
        private BusinessUtil $businessUtil
    ) {
    }

    public function getPublicSettings(int $businessId): array
    {
        $business = $this->businessUtil->getDetails($businessId);
        $settings = $this->storefrontSettings->get($businessId);

        return [
            'business_name' => $business->name ?? '',
            'logo_url' => business_logo_url($business) ?: null,
            'currency' => [
                'code' => $business->currency_code ?? 'EGP',
                'symbol' => $business->currency_symbol ?? 'EGP',
                'precision' => (int) ($business->currency_precision ?? 2),
                'symbol_placement' => $business->currency_symbol_placement ?? 'before',
            ],
            'contact' => $this->formatPublicContact($settings['contact'] ?? []),
            'social' => $settings['social'] ?? [],
            'announcement' => $settings['announcement'] ?? [],
            'theme' => [
                'accent_color' => $settings['theme']['accent_color'] ?? '#00d4aa',
            ],
            'cod_enabled' => (bool) ($settings['cod_enabled'] ?? false),
            'maintenance_mode' => (bool) ($settings['maintenance_mode'] ?? false),
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
        $email = trim((string) ($contact['email'] ?? ''));

        return [
            'phone' => $contact['phone'] ?? null,
            'whatsapp' => $contact['whatsapp'] ?? null,
            'email_encoded' => $email !== '' ? base64_encode($email) : null,
        ];
    }

    public function formatLocation(BusinessLocation $loc): array
    {
        return [
            'id' => $loc->id,
            'name' => $loc->name,
            'address' => $this->composeAddress($loc),
            'phone' => $loc->mobile,
            'email' => $loc->email,
            'enable_pickup' => (bool) ($loc->enable_pickup ?? false),
            'latitude' => $loc->latitude,
            'longitude' => $loc->longitude,
            'maps_url' => $this->mapsUrl($loc),
        ];
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

        $address = $this->composeAddress($loc);
        if ($address === '') {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query='.urlencode($address);
    }
}
