<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Services\Storefront\StorefrontSettingService;
use App\Utils\Util;
use Illuminate\Http\Request;

/**
 * Back-office settings for the public storefront (selling locations, COD, shipping, etc.).
 */
class StorefrontSettingController extends Controller
{
    public function __construct(
        private StorefrontSettingService $settings,
        private Util $commonUtil
    ) {
    }

    public function edit(Request $request)
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = (int) $request->session()->get('user.business_id');
        $settings = $this->settings->get($business_id);
        $locations = BusinessLocation::where('business_id', $business_id)
            ->Active()
            ->orderBy('name')
            ->get();

        return view('storefront.settings', compact('settings', 'locations'));
    }

    public function update(Request $request)
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = (int) $request->session()->get('user.business_id');

        $validated = $request->validate([
            'selling_location_ids' => 'nullable|array',
            'selling_location_ids.*' => 'integer|exists:business_locations,id',
            'default_fulfillment_location_id' => 'nullable|integer|exists:business_locations,id',
            'cod_enabled' => 'nullable|boolean',
            'maintenance_mode' => 'nullable|boolean',
            'shipping_flat_rate' => 'nullable|numeric|min:0',
            'shipping_free_threshold' => 'nullable|numeric|min:0',
            'shipping_hide_rates_until_address' => 'nullable|boolean',
            'courier_bosta_enabled' => 'nullable|boolean',
            'courier_bosta_api_key' => 'nullable|string|max:500',
            'courier_bosta_staging' => 'nullable|boolean',
            'contact_phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:191',
            'contact_whatsapp' => 'nullable|string|max:50',
            'announcement_message_en' => 'nullable|string|max:500',
            'announcement_message_ar' => 'nullable|string|max:500',
            'announcement_link' => 'nullable|string|max:500',
            'announcement_enabled' => 'nullable|boolean',
            'gateway_provider' => 'nullable|string|max:50',
            'gateway_api_key' => 'nullable|string|max:500',
            'gateway_enabled' => 'nullable|boolean',
            'fawry_merchant_code' => 'nullable|string|max:191',
            'fawry_security_key' => 'nullable|string|max:500',
            'fawry_staging' => 'nullable|boolean',
            'social_facebook' => 'nullable|string|max:500',
            'social_instagram' => 'nullable|string|max:500',
            'social_tiktok' => 'nullable|string|max:500',
            'social_youtube' => 'nullable|string|max:500',
            'theme_accent_color' => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{6})$/'],
            'sale_badge_mode' => 'nullable|in:percent,text',
            'sale_badge_text_en' => 'nullable|string|max:30',
            'sale_badge_text_ar' => 'nullable|string|max:30',
            'reward_points_name_en' => 'nullable|string|max:100',
            'reward_points_name_ar' => 'nullable|string|max:100',
            'catalog_show_availability_on_cards' => 'nullable|boolean',
            'turnstile_site_key' => 'nullable|string|max:191',
            'turnstile_secret_key' => 'nullable|string|max:500',
            'promo_codes_enabled_at_checkout' => 'nullable|boolean',
            'promo_codes_allow_stacking' => 'nullable|boolean',
            'payment_icons' => 'nullable|array|max:20',
            'payment_icons.*.label' => 'nullable|string|max:80',
            'payment_icons.*.url' => 'nullable|string|max:500',
            'payment_icons.*.existing_image' => 'nullable|string|max:191',
            'banners' => 'nullable|array|max:12',
            'banners.*.id' => 'nullable|string|max:40',
            'banners.*.placement' => 'nullable|in:home,category',
            'banners.*.category_slug' => 'nullable|string|max:191',
            'banners.*.title_en' => 'nullable|string|max:120',
            'banners.*.title_ar' => 'nullable|string|max:120',
            'banners.*.link' => 'nullable|string|max:500',
            'banners.*.url' => 'nullable|string|max:500',
            'banners.*.existing_image' => 'nullable|string|max:191',
            'banners.*.enabled' => 'nullable|boolean',
            'banners.*.sort_order' => 'nullable|integer|min:0|max:999',
            'newsletter_enabled' => 'nullable|boolean',
            'newsletter_provider' => 'nullable|in:mailchimp,mailerlite,aweber',
            'newsletter_double_opt_in' => 'nullable|boolean',
            'newsletter_mailchimp_api_key' => 'nullable|string|max:500',
            'newsletter_mailchimp_audience_id' => 'nullable|string|max:191',
            'newsletter_mailerlite_api_token' => 'nullable|string|max:500',
            'newsletter_mailerlite_group_id' => 'nullable|string|max:191',
            'newsletter_aweber_access_token' => 'nullable|string|max:2000',
            'newsletter_aweber_account_id' => 'nullable|string|max:191',
            'newsletter_aweber_list_id' => 'nullable|string|max:191',
        ]);

        $payload = [
            'selling_location_ids' => $validated['selling_location_ids'] ?? [],
            'default_fulfillment_location_id' => $validated['default_fulfillment_location_id'] ?? null,
            'cod_enabled' => $request->boolean('cod_enabled'),
            'maintenance_mode' => $request->boolean('maintenance_mode'),
            'shipping' => [
                'flat_rate' => (float) ($validated['shipping_flat_rate'] ?? ($this->settings->get($business_id)['shipping']['flat_rate'] ?? 0)),
                'free_shipping_threshold' => (float) ($validated['shipping_free_threshold'] ?? ($this->settings->get($business_id)['shipping']['free_shipping_threshold'] ?? 0)),
                'hide_rates_until_address' => $request->boolean('shipping_hide_rates_until_address'),
            ],
            'couriers' => [
                'bosta' => [
                    'enabled' => $request->boolean('courier_bosta_enabled'),
                    'api_key' => $validated['courier_bosta_api_key'] ?? null,
                    'staging' => $request->boolean('courier_bosta_staging'),
                ],
            ],
            'contact' => [
                'phone' => $validated['contact_phone'] ?? '',
                'email' => $validated['contact_email'] ?? '',
                'whatsapp' => $validated['contact_whatsapp'] ?? '',
            ],
            'announcement' => [
                'message' => [
                    'en' => $validated['announcement_message_en'] ?? '',
                    'ar' => $validated['announcement_message_ar'] ?? '',
                ],
                'link' => $validated['announcement_link'] ?? '',
                'enabled' => $request->boolean('announcement_enabled'),
            ],
            'gateway' => [
                'provider' => $validated['gateway_provider'] ?? null,
                'api_key' => $validated['gateway_api_key'] ?? null,
                'enabled' => $request->boolean('gateway_enabled'),
                'fawry' => [
                    'merchant_code' => $validated['fawry_merchant_code'] ?? '',
                    'security_key' => $validated['fawry_security_key'] ?? null,
                    'staging' => $request->boolean('fawry_staging'),
                ],
            ],
            'social' => [
                'facebook' => $validated['social_facebook'] ?? '',
                'instagram' => $validated['social_instagram'] ?? '',
                'tiktok' => $validated['social_tiktok'] ?? '',
                'youtube' => $validated['social_youtube'] ?? '',
            ],
            'theme' => [
                'accent_color' => $validated['theme_accent_color'] ?? '#00d4aa',
            ],
            'sale_badge' => [
                'mode' => $validated['sale_badge_mode'] ?? 'percent',
                'text' => [
                    'en' => $validated['sale_badge_text_en'] ?? 'Sale',
                    'ar' => $validated['sale_badge_text_ar'] ?? '',
                ],
            ],
            'reward_points' => [
                'name' => [
                    'en' => $validated['reward_points_name_en'] ?? 'Reward Points',
                    'ar' => $validated['reward_points_name_ar'] ?? '',
                ],
            ],
            'catalog' => [
                'show_availability_on_cards' => $request->boolean('catalog_show_availability_on_cards'),
            ],
            'turnstile' => [
                'site_key' => $validated['turnstile_site_key'] ?? '',
                'secret_key' => $validated['turnstile_secret_key'] ?? null,
            ],
            'promo_codes' => [
                'enabled_at_checkout' => $request->boolean('promo_codes_enabled_at_checkout'),
                'allow_stacking' => $request->boolean('promo_codes_allow_stacking'),
            ],
            'payment_icons' => $this->buildPaymentIconsPayload($request, $validated['payment_icons'] ?? []),
            'banners' => $this->buildBannersPayload($request, $validated['banners'] ?? []),
            'newsletter' => [
                'enabled' => $request->boolean('newsletter_enabled'),
                'provider' => $validated['newsletter_provider'] ?? null,
                'double_opt_in' => $request->boolean('newsletter_double_opt_in'),
                'mailchimp' => [
                    'api_key' => $validated['newsletter_mailchimp_api_key'] ?? null,
                    'audience_id' => $validated['newsletter_mailchimp_audience_id'] ?? '',
                ],
                'mailerlite' => [
                    'api_token' => $validated['newsletter_mailerlite_api_token'] ?? null,
                    'group_id' => $validated['newsletter_mailerlite_group_id'] ?? '',
                ],
                'aweber' => [
                    'access_token' => $validated['newsletter_aweber_access_token'] ?? null,
                    'account_id' => $validated['newsletter_aweber_account_id'] ?? '',
                    'list_id' => $validated['newsletter_aweber_list_id'] ?? '',
                ],
            ],
        ];

        $this->settings->save($business_id, $payload);

        $output = ['success' => true, 'msg' => __('lang_v1.success')];

        return redirect()->action([self::class, 'edit'])->with('status', $output);
    }

    /**
     * Build payment icon rows from form fields + optional uploads.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{label: string, image: string|null, url: string}>
     */
    private function buildPaymentIconsPayload(Request $request, array $rows): array
    {
        // Heal restrictive umask dirs (0700) so /uploads/storefront_payment_icons is web-readable.
        $this->commonUtil->ensurePublicUploadPermissions('storefront_payment_icons', null, true);

        $icons = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));
            $existing = basename(trim((string) ($row['existing_image'] ?? '')));
            $uploaded = null;

            // Flat file keys — Util::uploadFile does not support nested request names.
            $fileKey = 'payment_icon_image_'.$index;
            if ($request->hasFile($fileKey)) {
                try {
                    $uploaded = $this->commonUtil->uploadFile($request, $fileKey, 'storefront_payment_icons', 'image');
                } catch (\Throwable) {
                    $uploaded = null;
                }
            }

            $image = $uploaded ?: ($existing !== '' ? $existing : null);

            if ($label === '' && empty($image) && $url === '') {
                continue;
            }

            $icons[] = [
                'label' => $label,
                'image' => $image,
                'url' => empty($image) ? $url : '',
            ];
        }

        return $icons;
    }

    /**
     * Build promotional banner rows from form fields + optional uploads.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildBannersPayload(Request $request, array $rows): array
    {
        $this->commonUtil->ensurePublicUploadPermissions('storefront_banners', null, true);

        $banners = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $existing = basename(trim((string) ($row['existing_image'] ?? '')));
            $uploaded = null;
            $fileKey = 'banner_image_'.$index;
            if ($request->hasFile($fileKey)) {
                try {
                    $uploaded = $this->commonUtil->uploadFile($request, $fileKey, 'storefront_banners', 'image');
                } catch (\Throwable) {
                    $uploaded = null;
                }
            }

            $image = $uploaded ?: ($existing !== '' ? $existing : null);
            $url = trim((string) ($row['url'] ?? ''));

            $banners[] = [
                'id' => trim((string) ($row['id'] ?? '')),
                'placement' => ($row['placement'] ?? 'home') === 'category' ? 'category' : 'home',
                'category_slug' => trim((string) ($row['category_slug'] ?? '')),
                'title' => [
                    'en' => trim((string) ($row['title_en'] ?? '')),
                    'ar' => trim((string) ($row['title_ar'] ?? '')),
                ],
                'link' => trim((string) ($row['link'] ?? '')),
                'image' => $image,
                'url' => empty($image) ? $url : '',
                'enabled' => ! empty($row['enabled']),
                'sort_order' => (int) ($row['sort_order'] ?? $index),
            ];
        }

        return $banners;
    }
}
