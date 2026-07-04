<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Services\Storefront\StorefrontSettingService;
use Illuminate\Http\Request;

/**
 * Back-office settings for the public storefront (selling locations, COD, shipping, etc.).
 */
class StorefrontSettingController extends Controller
{
    public function __construct(private StorefrontSettingService $settings)
    {
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
        ]);

        $payload = [
            'selling_location_ids' => $validated['selling_location_ids'] ?? [],
            'default_fulfillment_location_id' => $validated['default_fulfillment_location_id'] ?? null,
            'cod_enabled' => $request->boolean('cod_enabled'),
            'maintenance_mode' => $request->boolean('maintenance_mode'),
            'shipping' => [
                'flat_rate' => (float) ($validated['shipping_flat_rate'] ?? 0),
                'free_shipping_threshold' => (float) ($validated['shipping_free_threshold'] ?? 0),
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
        ];

        $this->settings->save($business_id, $payload);

        $output = ['success' => true, 'msg' => __('lang_v1.success')];

        return redirect()->action([self::class, 'edit'])->with('status', $output);
    }
}
