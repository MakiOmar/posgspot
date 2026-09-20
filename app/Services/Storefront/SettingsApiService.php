<?php

namespace App\Services\Storefront;

use App\BusinessLocation;
use App\Category;
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

    public function getPublicSettings(
        int $businessId,
        string $locale = StorefrontLocale::DEFAULT,
        bool $shell = false
    ): array {
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

        $businessLogoUrl = business_logo_url($business) ?: null;

        return [
            'business_name' => $business->name ?? '',
            // Header / marketing: storefront Appearance logo when set, else POS business logo.
            'logo_url' => $this->storefrontSettings->logoPublicUrl(
                is_array($settings['logo'] ?? null) ? $settings['logo'] : null
            ) ?: $businessLogoUrl,
            // Always the POS Business Settings logo (splash / transition loader).
            'business_logo_url' => $businessLogoUrl,
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
            'favicon_url' => $this->storefrontSettings->faviconPublicUrl(
                is_array($settings['favicon'] ?? null) ? $settings['favicon'] : null
            ),
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
            // Shell loaders omit About team photos (page-local full GET /settings loads them).
            'about' => [
                'team' => $shell ? [] : $this->aboutTeamPayload($settings, $locale),
            ],
            'footer' => $this->footerPayload($settings, $locale),
            'shop_menu' => $this->shopMenuPayload($businessId, $settings, $locale),
            'banners' => $this->bannersPayload($settings, $locale),
            'newsletter' => [
                'enabled' => app(NewsletterProviderManager::class)->isEnabled($businessId),
            ],
            'repair' => $this->repairPayload($businessId),
            'digital' => [
                'enabled' => ! empty($settings['digital']['enabled']),
                'ask_whatsapp' => $this->digitalAskWhatsApp($settings),
                'pdp_faqs' => $this->digitalPdpFaqsPayload($settings, $locale),
            ],
            // OAuth providers — env-driven; do not collide with contact "social" links.
            'social_login' => [
                'google_enabled' => (bool) config('storefront.social_login.google.enabled'),
                'facebook_enabled' => (bool) config('storefront.social_login.facebook.enabled'),
            ],
            'support_chat' => [
                'enabled' => (bool) config('storefront.support_chat.enabled')
                    && filled(config('openai.api_key')),
            ],
            'custom_bundle' => [
                'enabled' => (bool) config('storefront.custom_bundle.enabled'),
                'min_items' => (int) config('storefront.custom_bundle.min_items', 2),
                'max_items' => (int) config('storefront.custom_bundle.max_items', 15),
            ],
            'sell_to_us' => [
                'enabled' => (bool) config('storefront.sell_to_us.enabled'),
            ],
            'community' => [
                'enabled' => (bool) config('storefront.community.enabled'),
            ],
            'request_product' => [
                'enabled' => (bool) config('storefront.request_product.enabled'),
            ],
            'locales' => ['en', 'ar'],
        ];
    }

    /**
     * Public branch list for footer / contact / store locator, or selling-only for checkout pickup.
     *
     * @param  bool  $sellingOnly  When true, only storefront selling locations. When false (default),
     *                             all active locations except those in selling_location_ids.
     *                             Always excludes locations with show_on_storefront = false.
     * @return list<array<string, mixed>>
     */
    public function getLocations(int $businessId, bool $sellingOnly = false): array
    {
        $sellingIds = $this->storefrontSettings->getSellingLocationIds($businessId);
        $sellingLookup = array_fill_keys($sellingIds, true);

        $query = BusinessLocation::where('business_id', $businessId)
            ->where('is_active', 1)
            ->visibleOnStorefront()
            ->orderBy('name');

        if ($sellingOnly) {
            if ($sellingIds === []) {
                return [];
            }
            $query->whereIn('id', $sellingIds);
        } elseif ($sellingIds !== []) {
            $query->whereNotIn('id', $sellingIds);
        }

        return $query
            ->get()
            ->map(function ($loc) use ($sellingLookup) {
                $row = $this->formatLocation($loc);
                $row['is_selling_location'] = isset($sellingLookup[(int) $loc->id]);

                return $row;
            })
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
        $geidea = is_array($gateway['geidea'] ?? null) ? $gateway['geidea'] : [];
        $region = (string) ($geidea['region'] ?? 'EGY-PROD');
        $mode = ($geidea['mode'] ?? 'test') === 'live' ? 'live' : 'test';

        return [
            'enabled' => $enabled,
            'provider' => $enabled ? (string) $provider : null,
            'label' => $enabled ? (string) (config("storefront-payments.labels.{$provider}") ?? ucfirst((string) $provider)) : null,
            'region' => $enabled && $provider === 'geidea' ? $region : null,
            'environment' => $enabled && $provider === 'geidea'
                ? ($mode === 'live' ? 'prod' : 'test')
                : null,
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
     * Locale-resolved Shop mega Physical column (empty when unset — clients use category fallback).
     *
     * @return array{physical: list<array<string, mixed>>}
     */
    private function shopMenuPayload(int $businessId, array $settings, string $locale): array
    {
        $menu = $this->storefrontSettings->normalizeShopMenu($settings['shop_menu'] ?? null);
        $physical = $menu['physical'] ?? [];
        if ($physical === []) {
            return ['physical' => []];
        }

        $ids = $this->collectShopMenuCategoryIds($physical);
        $categories = $ids === []
            ? collect()
            : Category::query()
                ->where('business_id', $businessId)
                ->where('category_type', 'product')
                ->whereIn('id', $ids)
                ->with(['storefrontTranslations' => fn ($q) => $q->where('locale', $locale)])
                ->get()
                ->keyBy('id');

        $resolved = [];
        foreach ($physical as $row) {
            if (! is_array($row)) {
                continue;
            }
            $node = $this->resolveShopMenuNode($row, $categories, $locale);
            if ($node !== null) {
                $resolved[] = $node;
            }
        }

        return ['physical' => $resolved];
    }

    /**
     * @param  list<array<string, mixed>>  $physical
     * @return list<int>
     */
    private function collectShopMenuCategoryIds(array $physical): array
    {
        $ids = [];
        $walk = function (array $rows) use (&$ids, &$walk): void {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                if (($row['type'] ?? '') === 'group') {
                    $walk(is_array($row['children'] ?? null) ? $row['children'] : []);
                    continue;
                }
                if ((int) ($row['category_id'] ?? 0) > 0) {
                    $ids[] = (int) $row['category_id'];
                }
            }
        };
        $walk($physical);

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  \Illuminate\Support\Collection<int, Category>  $categories
     * @return array<string, mixed>|null
     */
    private function resolveShopMenuNode(array $row, $categories, string $locale): ?array
    {
        if ((string) ($row['type'] ?? 'link') === 'group') {
            $groupLabel = trim($this->presenter->localizedSetting(
                $row['label'] ?? '',
                $locale,
                ''
            ));
            if ($groupLabel === '') {
                $groupLabel = trim((string) (is_array($row['label'] ?? null) ? ($row['label']['en'] ?? '') : ''));
            }
            if ($groupLabel === '') {
                return null;
            }
            $children = [];
            foreach ($row['children'] ?? [] as $child) {
                if (! is_array($child)) {
                    continue;
                }
                $resolved = $this->resolveShopMenuNode($child, $categories, $locale);
                if ($resolved !== null) {
                    $children[] = $resolved;
                }
            }

            return [
                'type' => 'group',
                'label' => $groupLabel,
                'children' => $children,
            ];
        }

        return $this->resolveShopMenuLink($row, $categories, $locale);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  \Illuminate\Support\Collection<int, Category>  $categories
     * @return array{type: string, label: string, href: string}|null
     */
    private function resolveShopMenuLink(array $row, $categories, string $locale): ?array
    {
        $categoryId = (int) ($row['category_id'] ?? 0);
        if ($categoryId <= 0 || ! $categories->has($categoryId)) {
            return null;
        }

        /** @var Category $category */
        $category = $categories->get($categoryId);
        $fields = $this->presenter->categoryFields($category, $locale);
        if ($fields === []) {
            // Configured menu: fall back to default-locale fields so the item still appears.
            $fields = $this->presenter->categoryFields($category, StorefrontLocale::DEFAULT);
        }
        if ($fields === []) {
            return null;
        }

        $override = trim($this->presenter->localizedSetting($row['label'] ?? '', $locale, ''));
        $label = $override !== '' ? $override : (string) ($fields['name'] ?? '');
        if ($label === '') {
            return null;
        }

        $slug = trim((string) ($fields['slug'] ?? ''));
        $href = $slug !== ''
            ? '/category/'.$slug
            : '/products?category_id='.$categoryId;

        return [
            'type' => 'link',
            'label' => $label,
            'href' => $href,
        ];
    }

    /**
     * Public footer menus with locale-resolved titles/labels (max 3 columns).
     *
     * @return array{
     *   contact_title: string,
     *   columns: list<array{id: string, title: string, links: list<array{id: string, label: string, url: string}>}>
     * }
     */
    private function footerPayload(array $settings, string $locale): array
    {
        $footer = $this->storefrontSettings->ensureFaqsQuickLink(
            $this->storefrontSettings->ensureSellToUsFooterLink(
                $this->storefrontSettings->ensureCustomBundleFooterLink(
                    $this->storefrontSettings->ensureDeleteAccountFooterLink(
                        $this->storefrontSettings->normalizeFooter($settings['footer'] ?? null)
                    )
                )
            )
        );
        $columns = [];
        foreach ($footer['columns'] as $col) {
            if (! is_array($col)) {
                continue;
            }
            $links = [];
            foreach ($col['links'] ?? [] as $link) {
                if (! is_array($link)) {
                    continue;
                }
                $url = trim((string) ($link['url'] ?? ''));
                if ($url === '') {
                    continue;
                }
                $links[] = [
                    'id' => (string) ($link['id'] ?? ''),
                    'label' => $this->presenter->localizedSetting($link['label'] ?? '', $locale, $url),
                    'url' => $url,
                ];
            }
            $columns[] = [
                'id' => (string) ($col['id'] ?? ''),
                'title' => $this->presenter->localizedSetting($col['title'] ?? '', $locale, 'Menu'),
                'links' => $links,
            ];
        }

        return [
            'contact_title' => $this->presenter->localizedSetting(
                $footer['contact_title'] ?? '',
                $locale,
                'Contact Info'
            ),
            'columns' => $columns,
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
     * About page team cards (name, localized role, photo, social URLs).
     *
     * @return list<array{name: string, role: string, image_url: string|null, social: array<string, string>}>
     */
    private function aboutTeamPayload(array $settings, string $locale): array
    {
        $rows = $settings['about_team'] ?? [];
        if (! is_array($rows) || $rows === []) {
            $rows = $this->storefrontSettings->defaultAboutTeam();
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $role = $row['role'] ?? [];
            if (is_string($role)) {
                $role = ['en' => $role, 'ar' => ''];
            }

            $social = [];
            $socialIn = is_array($row['social'] ?? null) ? $row['social'] : [];
            foreach ($this->storefrontSettings->aboutTeamSocialKeys() as $key) {
                $url = trim((string) ($socialIn[$key] ?? ''));
                if ($url !== '') {
                    $social[$key] = $url;
                }
            }

            $out[] = [
                'name' => $name,
                'role' => $this->presenter->localizedSetting($role, $locale, ''),
                'image_url' => $this->storefrontSettings->aboutTeamImagePublicUrl($row),
                'social' => $social,
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

    /**
     * Digits-only WhatsApp for digital PDP ask CTA (fallback to contact.whatsapp).
     */
    private function digitalAskWhatsApp(array $settings): ?string
    {
        $fromDigital = $this->storefrontSettings->normalizeWhatsAppDigits(
            (string) ($settings['digital']['ask_whatsapp'] ?? '')
        );
        if ($fromDigital !== '') {
            return $fromDigital;
        }
        $fromContact = $this->storefrontSettings->normalizeWhatsAppDigits(
            (string) ($settings['contact']['whatsapp'] ?? '')
        );

        return $fromContact !== '' ? $fromContact : null;
    }

    /**
     * Locale-resolved plain-text FAQ rows for the digital PDP.
     *
     * @return list<array{question: string, answer: string}>
     */
    private function digitalPdpFaqsPayload(array $settings, string $locale): array
    {
        $rows = $this->storefrontSettings->normalizeDigitalPdpFaqs(
            $settings['digital']['pdp_faqs'] ?? null
        );
        $out = [];
        foreach ($rows as $row) {
            $question = $this->presenter->localizedSetting($row['question'] ?? '', $locale, '');
            $answer = $this->presenter->localizedSetting($row['answer'] ?? '', $locale, '');
            if ($question === '' && $answer === '') {
                continue;
            }
            $out[] = [
                'question' => $question,
                'answer' => $answer,
            ];
        }

        return $out;
    }
}
