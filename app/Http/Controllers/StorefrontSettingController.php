<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Category;
use App\Services\Storefront\Homepage\HomepageSectionService;
use App\Services\Storefront\Homepage\SectionTypeRegistry;
use App\Services\Storefront\StorefrontBundleService;
use App\Services\Storefront\StorefrontMediaLibraryService;
use App\Services\Storefront\StorefrontSettingService;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Back-office settings for the public storefront (selling locations, COD, shipping, etc.).
 */
class StorefrontSettingController extends Controller
{
    public function __construct(
        private StorefrontSettingService $settings,
        private StorefrontBundleService $bundle,
        private HomepageSectionService $homepageSections,
        private SectionTypeRegistry $sectionTypes,
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

        $homepage_sections = $this->homepageSections->presentForAdmin(
            $settings['homepage_sections'] ?? []
        );
        $homepage_section_types = $this->sectionTypes->forAdmin();
        $homepage_categories = Category::where('business_id', $business_id)
            ->where('category_type', 'product')
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'parent_id'])
            ->map(fn (Category $c) => [
                'id' => (int) $c->id,
                'name' => (string) $c->name,
                'slug' => (string) $c->slug,
                'parent_id' => (int) ($c->parent_id ?? 0),
            ])
            ->values()
            ->all();

        return view('storefront.settings', compact(
            'settings',
            'locations',
            'homepage_sections',
            'homepage_section_types',
            'homepage_categories'
        ));
    }

    /**
     * AJAX save for the Vue homepage section builder.
     */
    public function updateHomepageSections(Request $request)
    {
        if (! auth()->user()->can('storefront.settings')) {
            return response()->json([
                'success' => false,
                'msg' => 'Unauthorized action.',
            ], 403);
        }

        $business_id = (int) $request->session()->get('user.business_id');
        $sectionsInput = $request->input('sections');
        $postBytes = is_string($sectionsInput)
            ? strlen($sectionsInput)
            : strlen((string) json_encode($sectionsInput));

        Log::warning('storefront.homepage_sections.request', [
            'business_id' => $business_id,
            'post_bytes' => $postBytes,
            'mem_bytes' => memory_get_usage(true),
            'peak_bytes' => memory_get_peak_usage(true),
        ]);

        if ($postBytes > StorefrontSettingService::MAX_HOMEPAGE_SECTIONS_POST_BYTES) {
            return response()->json([
                'success' => false,
                'msg' => 'Homepage payload too large. Use the media library for icons (no inline SVG).',
            ], 422);
        }

        $sections = $sectionsInput;
        if (is_string($sections)) {
            $decoded = json_decode($sections, true);
            $sections = is_array($decoded) ? $decoded : [];
        }

        try {
            $normalized = $this->settings->saveHomepageSections(
                $business_id,
                is_array($sections) ? $sections : []
            );
        } catch (\Throwable $e) {
            Log::error('storefront.homepage_sections.save.failed', [
                'business_id' => $business_id,
                'error' => $e->getMessage(),
                'mem_bytes' => memory_get_usage(true),
                'peak_bytes' => memory_get_peak_usage(true),
            ]);

            return response()->json([
                'success' => false,
                'msg' => 'Save failed (server memory). Run: php artisan storefront:scrub-inline-svg',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'msg' => 'Homepage sections saved.',
            // Present the normalized payload — do not re-load the full settings blob.
            'sections' => $this->homepageSections->presentForAdmin($normalized),
        ]);
    }

    /**
     * List storefront media library assets for the homepage builder picker.
     */
    public function listMedia(Request $request, StorefrontMediaLibraryService $library)
    {
        if (! $this->canAccessStorefrontMediaLibrary()) {
            return response()->json(['success' => false, 'msg' => 'Unauthorized action.'], 403);
        }

        $businessId = (int) $request->session()->get('user.business_id');
        $kind = $request->query('kind');
        $kind = is_string($kind) ? $kind : null;
        $q = $request->query('q');
        $q = is_string($q) ? $q : null;
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(48, (int) $request->query('per_page', 24)));

        $result = $library->list($businessId, $kind, $q, $page, $perPage);

        return response()->json([
            'success' => true,
            'items' => $result['items'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * Upload an image or SVG into the storefront media library (checksum-deduped).
     * Returns storage path + public URL. SVG content is never inlined.
     */
    public function uploadHomepageMedia(Request $request, StorefrontMediaLibraryService $library)
    {
        if (! $this->canAccessStorefrontMediaLibrary()) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'image' => [
                'required',
                'file',
                'max:5120',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/svg+xml,text/plain,text/xml,application/xml,application/octet-stream',
            ],
        ]);

        $businessId = (int) $request->session()->get('user.business_id');

        try {
            $result = $library->storeUploadedFile(
                $businessId,
                $request->file('image'),
                auth()->id()
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'msg' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::warning('Storefront media upload failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ], 422);
        }

        $presented = $library->present($result['media']);

        return response()->json([
            'success' => true,
            'media_id' => $presented['id'],
            'image' => $presented['image'],
            'image_url' => $presented['image_url'],
            'icon_kind' => $result['media']->kind === 'svg' ? 'svg' : 'image',
            'deduped' => ! $result['created'],
        ]);
    }

    /**
     * Soft-delete a library asset and remove its file from disk.
     */
    public function destroyMedia(Request $request, int $id, StorefrontMediaLibraryService $library)
    {
        if (! auth()->user()->can('storefront.settings')) {
            return response()->json(['success' => false, 'msg' => 'Unauthorized action.'], 403);
        }

        $businessId = (int) $request->session()->get('user.business_id');
        if (! $library->delete($businessId, $id)) {
            return response()->json(['success' => false, 'msg' => 'Media not found.'], 404);
        }

        return response()->json(['success' => true, 'msg' => 'Media deleted.']);
    }

    /**
     * Product editors may list/upload library assets for gallery pick; delete stays settings-only.
     */
    private function canAccessStorefrontMediaLibrary(): bool
    {
        $user = auth()->user();

        return $user
            && (
                $user->can('storefront.settings')
                || $user->can('product.create')
                || $user->can('product.update')
            );
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
            'favicon_url' => 'nullable|string|max:500',
            'favicon_existing_image' => 'nullable|string|max:191',
            'favicon_clear' => 'nullable|boolean',
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
            'digital_enabled' => 'nullable|boolean',
            'digital_accounts_store_profile_id' => 'nullable|integer|min:1',
            'digital_primary_product_id' => 'nullable|integer|min:1',
            'digital_secondary_product_id' => 'nullable|integer|min:1',
            'digital_gift_card_product_id' => 'nullable|integer|min:1',
            'digital_pos_document_type' => 'nullable|in:sell,quotation',
            'digital_expose_credentials_to_customer' => 'nullable|boolean',
            'footer_contact_title_en' => 'nullable|string|max:80',
            'footer_contact_title_ar' => 'nullable|string|max:80',
            'footer_columns' => 'nullable|array|max:3',
            'footer_columns.*.id' => 'nullable|string|max:40',
            'footer_columns.*.title_en' => 'nullable|string|max:80',
            'footer_columns.*.title_ar' => 'nullable|string|max:80',
            'footer_columns.*.links' => 'nullable|array|max:12',
            'footer_columns.*.links.*.id' => 'nullable|string|max:40',
            'footer_columns.*.links.*.label_en' => 'nullable|string|max:80',
            'footer_columns.*.links.*.label_ar' => 'nullable|string|max:80',
            'footer_columns.*.links.*.url' => 'nullable|string|max:500',
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
            'digital' => [
                'enabled' => $request->boolean('digital_enabled'),
                'accounts_store_profile_id' => (int) ($validated['digital_accounts_store_profile_id'] ?? 17),
                'primary_product_id' => isset($validated['digital_primary_product_id'])
                    ? (int) $validated['digital_primary_product_id']
                    : null,
                'secondary_product_id' => isset($validated['digital_secondary_product_id'])
                    ? (int) $validated['digital_secondary_product_id']
                    : null,
                'gift_card_product_id' => isset($validated['digital_gift_card_product_id'])
                    ? (int) $validated['digital_gift_card_product_id']
                    : null,
                'pos_document_type' => ($validated['digital_pos_document_type'] ?? 'sell') === 'quotation'
                    ? 'quotation'
                    : 'sell',
                'expose_credentials_to_customer' => $request->boolean('digital_expose_credentials_to_customer'),
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
            'favicon' => $this->buildFaviconPayload($request, $validated),
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
            'footer' => $this->buildFooterPayload($validated),
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
     * Download full storefront config as a ZIP (settings, shipping, media, coupons, overlays, translations).
     * Secrets are redacted. Orders / wishlist / reviews are excluded.
     */
    public function export(Request $request)
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = (int) $request->session()->get('user.business_id');

        try {
            $zipPath = $this->bundle->exportToTempZip($business_id);
        } catch (\Throwable $e) {
            Log::error('Storefront settings export failed', [
                'business_id' => $business_id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->action([self::class, 'edit'])->with('status', [
                'success' => false,
                'msg' => $e->getMessage() ?: __('messages.something_went_wrong'),
            ]);
        }

        $filename = 'storefront-bundle-'.$business_id.'-'.now()->format('Y-m-d-His').'.zip';

        return response()->download($zipPath, $filename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Import a storefront ZIP bundle or legacy settings JSON.
     */
    public function import(Request $request)
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'import_file' => 'required|file|max:102400',
        ]);

        $file = $request->file('import_file');
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $business_id = (int) $request->session()->get('user.business_id');

        try {
            $result = $this->bundle->importPath($business_id, $file->getRealPath(), $ext);
        } catch (\InvalidArgumentException $e) {
            return redirect()->action([self::class, 'edit'])->with('status', [
                'success' => false,
                'msg' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Storefront settings import failed', [
                'business_id' => $business_id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->action([self::class, 'edit'])->with('status', [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ]);
        }

        $sections = implode(', ', $result['sections'] ?? []);
        $media = is_array($result['details']['media'] ?? null) ? $result['details']['media'] : null;
        $mediaMsg = '';
        if ($media !== null) {
            $mediaMsg = sprintf(
                ' Media files: %d copied, %d skipped.',
                (int) ($media['copied'] ?? 0),
                (int) ($media['skipped'] ?? 0)
            );
            if ((int) ($media['remapped_library'] ?? 0) > 0) {
                $mediaMsg .= sprintf(
                    ' Library folder remapped %d→%d.',
                    (int) ($media['source_business_id'] ?? 0),
                    (int) ($media['target_business_id'] ?? 0)
                );
            }
        }
        $library = is_array($result['details']['media_library'] ?? null) ? $result['details']['media_library'] : null;
        if ($library !== null) {
            $mediaMsg .= sprintf(
                ' Media library rows: %d inserted, %d updated, %d skipped.',
                (int) ($library['inserted'] ?? 0),
                (int) ($library['updated'] ?? 0),
                (int) ($library['skipped'] ?? 0)
            );
        }

        return redirect()->action([self::class, 'edit'])->with('status', [
            'success' => true,
            'msg' => 'Storefront import complete ('.$sections.').'.$mediaMsg
                .' Secrets left blank were kept unchanged. Catalog overlays/translations apply only when matching slugs/SKUs exist.',
        ]);
    }

    /**
     * Build favicon settings from Appearance tab (upload and/or external URL).
     *
     * @param  array<string, mixed>  $validated
     * @return array{image: string|null, url: string}
     */
    private function buildFaviconPayload(Request $request, array $validated): array
    {
        $this->commonUtil->ensurePublicUploadPermissions('storefront_favicon', null, true);

        if ($request->boolean('favicon_clear')) {
            return ['image' => null, 'url' => ''];
        }

        $existing = basename(trim((string) ($validated['favicon_existing_image'] ?? '')));
        $url = trim((string) ($validated['favicon_url'] ?? ''));
        $uploaded = null;

        if ($request->hasFile('favicon_image')) {
            try {
                // Prefer image MIME (png/svg/webp/jpeg); fall back to document for .ico.
                try {
                    $uploaded = $this->commonUtil->uploadFile($request, 'favicon_image', 'storefront_favicon', 'image');
                } catch (\Throwable $e) {
                    $uploaded = $this->commonUtil->uploadFile($request, 'favicon_image', 'storefront_favicon', 'document');
                }
            } catch (\Throwable $e) {
                \Log::warning('storefront.favicon.upload_failed', ['error' => $e->getMessage()]);
            }
        }

        if (is_string($uploaded) && $uploaded !== '') {
            return ['image' => basename($uploaded), 'url' => ''];
        }

        if ($existing !== '') {
            return ['image' => $existing, 'url' => ''];
        }

        return ['image' => null, 'url' => $url];
    }

    /**
     * Build footer menus from Storefront Settings → Footer tab fields.
     *
     * @param  array<string, mixed>  $validated
     * @return array{contact_title: array{en: string, ar: string}, columns: list<array<string, mixed>>}
     */
    private function buildFooterPayload(array $validated): array
    {
        $columns = [];
        foreach ($validated['footer_columns'] ?? [] as $col) {
            if (! is_array($col)) {
                continue;
            }
            $links = [];
            foreach ($col['links'] ?? [] as $link) {
                if (! is_array($link)) {
                    continue;
                }
                $links[] = [
                    'id' => $link['id'] ?? '',
                    'label' => [
                        'en' => $link['label_en'] ?? '',
                        'ar' => $link['label_ar'] ?? '',
                    ],
                    'url' => $link['url'] ?? '',
                ];
            }
            $columns[] = [
                'id' => $col['id'] ?? '',
                'title' => [
                    'en' => $col['title_en'] ?? '',
                    'ar' => $col['title_ar'] ?? '',
                ],
                'links' => $links,
            ];
        }

        return [
            'contact_title' => [
                'en' => $validated['footer_contact_title_en'] ?? 'Contact Info',
                'ar' => $validated['footer_contact_title_ar'] ?? '',
            ],
            'columns' => $columns,
        ];
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
