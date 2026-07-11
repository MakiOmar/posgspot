@extends('layouts.app')
@section('title', 'Storefront Settings')

@section('content')
{{-- Storefront settings — public shop configuration (tabbed for length) --}}
<section class="content-header">
    <h1>Storefront Settings</h1>
</section>

<section class="content">
    @if (session('status'))
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            {{ session('status')['msg'] ?? 'Saved.' }}
        </div>
    @endif

    {!! Form::open(['url' => action([\App\Http\Controllers\StorefrontSettingController::class, 'update']), 'method' => 'post', 'id' => 'storefront_settings_form', 'files' => true]) !!}

    <div class="nav-tabs-custom">
        <ul class="nav nav-tabs" id="storefront_settings_tabs">
            <li class="active">
                <a href="#tab_general" data-toggle="tab" aria-expanded="true">
                    <i class="fa fa-cog"></i> General
                </a>
            </li>
            <li>
                <a href="#tab_appearance" data-toggle="tab" aria-expanded="false">
                    <i class="fa fa-paint-brush"></i> Appearance
                </a>
            </li>
            <li>
                <a href="#tab_banners" data-toggle="tab" aria-expanded="false">
                    <i class="fa fa-picture-o"></i> Banners
                </a>
            </li>
            <li>
                <a href="#tab_contact" data-toggle="tab" aria-expanded="false">
                    <i class="fa fa-phone"></i> Contact &amp; social
                </a>
            </li>
            <li>
                <a href="#tab_checkout" data-toggle="tab" aria-expanded="false">
                    <i class="fa fa-shopping-cart"></i> Checkout
                </a>
            </li>
            <li>
                <a href="#tab_payments" data-toggle="tab" aria-expanded="false">
                    <i class="fa fa-credit-card"></i> Payments
                </a>
            </li>
            <li>
                <a href="#tab_newsletter" data-toggle="tab" aria-expanded="false">
                    <i class="fa fa-envelope"></i> Newsletter
                </a>
            </li>
            <li>
                <a href="#tab_security" data-toggle="tab" aria-expanded="false">
                    <i class="fa fa-shield"></i> Security
                </a>
            </li>
        </ul>

        <div class="tab-content">
            {{-- General: locations, COD, maintenance, shipping --}}
            <div class="tab-pane active" id="tab_general">
                <div class="alert alert-info">
                    Select which locations sell online. If none are selected, the storefront catalog will show no products.
                </div>

                <div class="form-group">
                    {!! Form::label('selling_location_ids', 'Selling locations *') !!}
                    {!! Form::select('selling_location_ids[]', $locations->pluck('name', 'id'), $settings['selling_location_ids'] ?? [], ['class' => 'form-control select2', 'multiple' => true, 'style' => 'width:100%']) !!}
                </div>

                <div class="form-group">
                    {!! Form::label('default_fulfillment_location_id', 'Default fulfillment location') !!}
                    {!! Form::select('default_fulfillment_location_id', ['' => '— Select —'] + $locations->pluck('name', 'id')->all(), $settings['default_fulfillment_location_id'] ?? null, ['class' => 'form-control select2', 'style' => 'width:100%']) !!}
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="checkbox">
                            <label>
                                {!! Form::checkbox('cod_enabled', 1, $settings['cod_enabled'] ?? false) !!} Enable Cash on Delivery
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="checkbox">
                            <label>
                                {!! Form::checkbox('maintenance_mode', 1, $settings['maintenance_mode'] ?? false) !!} Maintenance mode
                            </label>
                        </div>
                    </div>
                </div>

                <hr>
                <h4>Shipping zones</h4>
                <p class="help-block">
                    Rates are managed by zone (country / governorate), similar to WooCommerce.
                    Legacy flat rate / free threshold were imported into a default Egypt zone on first load.
                </p>
                <div class="checkbox" style="margin-bottom: 1rem;">
                    <label>
                        {!! Form::checkbox('shipping_hide_rates_until_address', 1, $settings['shipping']['hide_rates_until_address'] ?? true) !!}
                        Hide delivery rates until country and governorate are entered at checkout
                    </label>
                </div>
                <div id="storefront_shipping_zones_app">
                    <p class="text-muted">Loading zones…</p>
                </div>
                <div class="btn-group" style="margin-top: 0.75rem;">
                    <button type="button" class="btn btn-default btn-sm" id="sf_shipping_reload_zones">Reload zones</button>
                    <button type="button" class="btn btn-primary btn-sm" id="sf_shipping_add_zone">Add zone</button>
                </div>

                <h5 style="margin-top: 1.5rem;">Shipping classes</h5>
                <p class="help-block">Assign classes on products; optional per-class costs on flat-rate methods (<code>class_costs</code>).</p>
                <div id="storefront_shipping_classes_app"><p class="text-muted">Loading…</p></div>
                <button type="button" class="btn btn-default btn-sm" id="sf_shipping_add_class">Add shipping class</button>

                <hr>
                <h4>Couriers (optional)</h4>
                <div class="row">
                    <div class="col-md-4">
                        <div class="checkbox">
                            <label>
                                {!! Form::checkbox('courier_bosta_enabled', 1, $settings['couriers']['bosta']['enabled'] ?? false) !!} Enable Bosta
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('courier_bosta_api_key', 'Bosta API key') !!}
                            {!! Form::password('courier_bosta_api_key', ['class' => 'form-control', 'placeholder' => !empty($settings['couriers']['bosta']['api_key']) ? '••••••••' : '', 'autocomplete' => 'new-password']) !!}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="checkbox">
                            <label>
                                {!! Form::checkbox('courier_bosta_staging', 1, $settings['couriers']['bosta']['staging'] ?? true) !!} Bosta staging
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Appearance: theme, sale badge, catalog cards, announcement --}}
            <div class="tab-pane" id="tab_appearance">
                <h4>Theme</h4>
                {{-- Storefront accent color: drives the Qwik --gs-accent CSS variable --}}
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('theme_accent_color', 'Accent color') !!}
                            <div class="input-group">
                                {!! Form::text('theme_accent_color', $settings['theme']['accent_color'] ?? '#00d4aa', ['class' => 'form-control', 'id' => 'theme_accent_color', 'placeholder' => '#00d4aa', 'maxlength' => 7]) !!}
                                <span class="input-group-addon" style="padding:0;">
                                    {{-- Native color picker mirrors the text field --}}
                                    <input type="color" id="theme_accent_color_picker" value="{{ $settings['theme']['accent_color'] ?? '#00d4aa' }}" style="border:0;width:38px;height:34px;cursor:pointer;background:transparent;">
                                </span>
                            </div>
                            <p class="help-block">Primary highlight color used across the public storefront (buttons, links, badges).</p>
                        </div>
                    </div>
                </div>

                <hr>
                <h4>Sale badge (product cards)</h4>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('sale_badge_mode', 'Badge style') !!}
                            {!! Form::select('sale_badge_mode', [
                                'percent' => 'Discount percent (e.g. -20%)',
                                'text' => 'Custom text',
                            ], $settings['sale_badge']['mode'] ?? 'percent', ['class' => 'form-control']) !!}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('sale_badge_text_en', 'Badge text EN (when style is Custom text)') !!}
                            {!! Form::text('sale_badge_text_en', is_array($settings['sale_badge']['text'] ?? null) ? ($settings['sale_badge']['text']['en'] ?? 'Sale') : ($settings['sale_badge']['text'] ?? 'Sale'), ['class' => 'form-control', 'maxlength' => 30]) !!}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('sale_badge_text_ar', 'Badge text AR') !!}
                            {!! Form::text('sale_badge_text_ar', is_array($settings['sale_badge']['text'] ?? null) ? ($settings['sale_badge']['text']['ar'] ?? '') : '', ['class' => 'form-control', 'maxlength' => 30]) !!}
                        </div>
                    </div>
                </div>
                <p class="help-block">Shown on product cards when an online sale price is set on the product in POS (lower than the regular selling price).</p>

                <hr>
                <h4>Product listing cards</h4>
                <div class="checkbox">
                    <label>
                        {!! Form::checkbox('catalog_show_availability_on_cards', 1, $settings['catalog']['show_availability_on_cards'] ?? true) !!}
                        Show &ldquo;Check store availability&rdquo; on out-of-stock product cards
                    </label>
                </div>
                <p class="help-block">When enabled, out-of-stock items on the shop listing show a button to open the per-store stock modal (same as the product page).</p>

                <hr>
                <h4>Announcement bar</h4>
                <div class="checkbox">
                    <label>
                        {!! Form::checkbox('announcement_enabled', 1, $settings['announcement']['enabled'] ?? false) !!} Show announcement bar
                    </label>
                </div>
                <div class="form-group">
                    {!! Form::label('announcement_message_en', 'Message (English)') !!}
                    @php $annMsg = $settings['announcement']['message'] ?? ''; @endphp
                    {!! Form::text('announcement_message_en', is_array($annMsg) ? ($annMsg['en'] ?? '') : $annMsg, ['class' => 'form-control']) !!}
                </div>
                <div class="form-group">
                    {!! Form::label('announcement_message_ar', 'Message (Arabic)') !!}
                    {!! Form::text('announcement_message_ar', is_array($annMsg) ? ($annMsg['ar'] ?? '') : '', ['class' => 'form-control', 'dir' => 'rtl']) !!}
                </div>
                <div class="form-group">
                    {!! Form::label('announcement_link', 'Link (optional)') !!}
                    {!! Form::text('announcement_link', $settings['announcement']['link'] ?? '', ['class' => 'form-control']) !!}
                </div>
            </div>

            {{-- Promotional banners: homepage + category --}}
            <div class="tab-pane" id="tab_banners">
                <div class="alert alert-info">
                    Upload or link images for the storefront homepage and category pages. Category banners need a matching category slug.
                </div>
                @php $promoBanners = $settings['banners'] ?? []; @endphp
                <div class="table-responsive">
                    <table class="table table-bordered" id="banners_table">
                        <thead>
                            <tr>
                                <th style="width:12%">Placement</th>
                                <th style="width:12%">Category slug</th>
                                <th style="width:14%">Title EN / AR</th>
                                <th style="width:14%">Link</th>
                                <th style="width:14%">Image URL</th>
                                <th style="width:12%">Upload</th>
                                <th style="width:6%">Sort</th>
                                <th style="width:6%">On</th>
                                <th style="width:8%">Preview</th>
                                <th style="width:4%"></th>
                            </tr>
                        </thead>
                        <tbody id="banners_tbody">
                            @foreach ($promoBanners as $biIndex => $banner)
                                @php
                                    $bTitle = $banner['title'] ?? [];
                                    $bImage = $banner['image'] ?? null;
                                    $bUrl = $banner['url'] ?? '';
                                    $bPreview = ! empty($bImage)
                                        ? asset('uploads/storefront_banners/'.$bImage)
                                        : $bUrl;
                                @endphp
                                <tr class="banner-row" data-index="{{ $biIndex }}">
                                    <td>
                                        <input type="hidden" name="banners[{{ $biIndex }}][id]" value="{{ $banner['id'] ?? '' }}">
                                        <select class="form-control banner-placement" name="banners[{{ $biIndex }}][placement]">
                                            <option value="home" @if(($banner['placement'] ?? 'home') === 'home') selected @endif>Home</option>
                                            <option value="category" @if(($banner['placement'] ?? '') === 'category') selected @endif>Category</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control banner-category-slug" name="banners[{{ $biIndex }}][category_slug]" value="{{ $banner['category_slug'] ?? '' }}" maxlength="191" placeholder="e.g. playstation" @if(($banner['placement'] ?? 'home') !== 'category') disabled @endif>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" name="banners[{{ $biIndex }}][title_en]" value="{{ is_array($bTitle) ? ($bTitle['en'] ?? '') : '' }}" maxlength="120" placeholder="EN alt/title">
                                        <input type="text" class="form-control" name="banners[{{ $biIndex }}][title_ar]" value="{{ is_array($bTitle) ? ($bTitle['ar'] ?? '') : '' }}" maxlength="120" placeholder="AR" dir="rtl" style="margin-top:4px;">
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" name="banners[{{ $biIndex }}][link]" value="{{ $banner['link'] ?? '' }}" maxlength="500" placeholder="/products or https://…">
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" name="banners[{{ $biIndex }}][url]" value="{{ $bUrl }}" maxlength="500" placeholder="https://…">
                                        <input type="hidden" name="banners[{{ $biIndex }}][existing_image]" value="{{ $bImage }}">
                                    </td>
                                    <td>
                                        <input type="file" class="form-control" name="banner_image_{{ $biIndex }}" accept="image/*">
                                    </td>
                                    <td>
                                        <input type="number" class="form-control" name="banners[{{ $biIndex }}][sort_order]" value="{{ (int) ($banner['sort_order'] ?? $biIndex) }}" min="0" max="999">
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox" name="banners[{{ $biIndex }}][enabled]" value="1" @if(!empty($banner['enabled'])) checked @endif>
                                    </td>
                                    <td class="text-center">
                                        @if ($bPreview)
                                            <img src="{{ $bPreview }}" alt="" style="max-height:36px;max-width:72px;">
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-danger btn-xs remove-banner" title="Remove">&times;</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-default btn-sm" id="add_banner_row">
                    <i class="fa fa-plus"></i> Add banner
                </button>
                <p class="help-block">Max 12 banners. Prefer wide images (~1200×400). Relative links like <code>/products</code> work on the storefront.</p>
            </div>

            {{-- Contact & social --}}
            <div class="tab-pane" id="tab_contact">
                <h4>Contact</h4>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('contact_phone', 'Phone') !!}
                            {!! Form::text('contact_phone', $settings['contact']['phone'] ?? '', ['class' => 'form-control']) !!}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('contact_email', 'Email') !!}
                            {!! Form::email('contact_email', $settings['contact']['email'] ?? '', ['class' => 'form-control']) !!}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('contact_whatsapp', 'WhatsApp') !!}
                            {!! Form::text('contact_whatsapp', $settings['contact']['whatsapp'] ?? '', ['class' => 'form-control']) !!}
                        </div>
                    </div>
                </div>

                <hr>
                <h4>Social links</h4>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('social_facebook', 'Facebook URL') !!}
                            {!! Form::text('social_facebook', $settings['social']['facebook'] ?? '', ['class' => 'form-control']) !!}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('social_instagram', 'Instagram URL') !!}
                            {!! Form::text('social_instagram', $settings['social']['instagram'] ?? '', ['class' => 'form-control']) !!}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('social_tiktok', 'TikTok URL') !!}
                            {!! Form::text('social_tiktok', $settings['social']['tiktok'] ?? '', ['class' => 'form-control']) !!}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('social_youtube', 'YouTube URL') !!}
                            {!! Form::text('social_youtube', $settings['social']['youtube'] ?? '', ['class' => 'form-control']) !!}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Checkout: promo codes, reward points --}}
            <div class="tab-pane" id="tab_checkout">
                <h4 id="promo-checkout-settings">Promo codes (storefront checkout)</h4>
                @php $promoCodes = $settings['promo_codes'] ?? []; @endphp
                <div class="row">
                    <div class="col-md-6">
                        <div class="checkbox">
                            <label>
                                {!! Form::checkbox('promo_codes_enabled_at_checkout', 1, $promoCodes['enabled_at_checkout'] ?? true) !!}
                                Show promo code field at cart/checkout (signed-in customers)
                            </label>
                        </div>
                        <p class="help-block">When off, customers cannot apply promo codes online even if codes exist in POS.</p>
                    </div>
                    <div class="col-md-6">
                        <div class="checkbox">
                            <label>
                                {!! Form::checkbox('promo_codes_allow_stacking', 1, $promoCodes['allow_stacking'] ?? false) !!}
                                Allow multiple promo codes per order
                            </label>
                        </div>
                        <p class="help-block">When off, only one promo code can be applied per checkout.</p>
                    </div>
                </div>

                <hr>
                <h4>Reward points (storefront label)</h4>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            {!! Form::label('reward_points_name_en', 'Name (English)') !!}
                            @php $rpName = $settings['reward_points']['name'] ?? ['en' => 'Reward Points', 'ar' => '']; @endphp
                            {!! Form::text('reward_points_name_en', is_array($rpName) ? ($rpName['en'] ?? '') : $rpName, ['class' => 'form-control']) !!}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            {!! Form::label('reward_points_name_ar', 'Name (Arabic)') !!}
                            {!! Form::text('reward_points_name_ar', is_array($rpName) ? ($rpName['ar'] ?? '') : '', ['class' => 'form-control', 'dir' => 'rtl']) !!}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Payments: footer icons + gateway --}}
            <div class="tab-pane" id="tab_payments">
                <h4 id="payment-icons-settings">Footer payment icons</h4>
                <p class="help-block">
                    Icons shown in the storefront footer (informational only — not checkout methods).
                    Upload an image or paste an external image URL. Max 20 icons.
                </p>
                @php
                    $paymentIcons = $settings['payment_icons'] ?? [];
                    if (! is_array($paymentIcons)) {
                        $paymentIcons = [];
                    }
                @endphp
                <div class="table-responsive">
                    <table class="table table-bordered" id="payment_icons_table">
                        <thead>
                            <tr>
                                <th style="width: 22%;">Label (alt text)</th>
                                <th style="width: 28%;">Image URL (optional)</th>
                                <th style="width: 28%;">Upload image</th>
                                <th style="width: 14%;">Preview</th>
                                <th style="width: 8%;"></th>
                            </tr>
                        </thead>
                        <tbody id="payment_icons_tbody">
                            @forelse ($paymentIcons as $piIndex => $pi)
                                @php
                                    $piLabel = $pi['label'] ?? '';
                                    $piImage = $pi['image'] ?? null;
                                    $piUrl = $pi['url'] ?? '';
                                    $piPreview = ! empty($piImage)
                                        ? asset('uploads/storefront_payment_icons/'.$piImage)
                                        : $piUrl;
                                @endphp
                                <tr class="payment-icon-row" data-index="{{ $piIndex }}">
                                    <td>
                                        <input type="text" class="form-control" name="payment_icons[{{ $piIndex }}][label]" value="{{ $piLabel }}" maxlength="80" placeholder="e.g. Visa">
                                        <input type="hidden" name="payment_icons[{{ $piIndex }}][existing_image]" value="{{ $piImage }}">
                                    </td>
                                    <td>
                                        <input type="text" class="form-control payment-icon-url" name="payment_icons[{{ $piIndex }}][url]" value="{{ $piUrl }}" maxlength="500" placeholder="https://…">
                                    </td>
                                    <td>
                                        <input type="file" class="form-control payment-icon-file" name="payment_icon_image_{{ $piIndex }}" accept="image/*">
                                    </td>
                                    <td class="text-center">
                                        @if (! empty($piPreview))
                                            <img src="{{ $piPreview }}" alt="" class="payment-icon-preview" style="max-height: 32px; max-width: 64px;">
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-danger btn-xs remove-payment-icon" title="Remove">&times;</button>
                                    </td>
                                </tr>
                            @empty
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-default btn-sm" id="add_payment_icon_row">
                    <i class="fa fa-plus"></i> Add payment icon
                </button>

                <hr>
                <h4>Payment gateway</h4>
                <div class="checkbox">
                    <label>
                        {!! Form::checkbox('gateway_enabled', 1, $settings['gateway']['enabled'] ?? false) !!} Enable online payments
                    </label>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('gateway_provider', 'Provider') !!}
                            {!! Form::select('gateway_provider', ['' => '—', 'fawry' => 'FawryPay', 'myfatoorah' => 'MyFatoorah', 'paymob' => 'Paymob'], $settings['gateway']['provider'] ?? '', ['class' => 'form-control', 'id' => 'gateway_provider']) !!}
                        </div>
                    </div>
                </div>
                <div id="gateway_fawry_fields">
                    @php
                        $fawry = $settings['gateway']['fawry'] ?? [];
                        $webhookUrl = url('/api/storefront/v1/payments/fawry/webhook');
                    @endphp
                    <div class="alert alert-info">
                        Fawry callback URL (register in Fawry dashboard): <strong>{{ $webhookUrl }}</strong>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                {!! Form::label('fawry_merchant_code', 'Merchant code') !!}
                                {!! Form::text('fawry_merchant_code', $fawry['merchant_code'] ?? '', ['class' => 'form-control']) !!}
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                {!! Form::label('fawry_security_key', 'Security key (leave blank to keep current)') !!}
                                {!! Form::password('fawry_security_key', ['class' => 'form-control', 'autocomplete' => 'new-password']) !!}
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="checkbox" style="margin-top: 28px;">
                                <label>
                                    {!! Form::checkbox('fawry_staging', 1, $fawry['staging'] ?? false) !!} Use staging environment
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="gateway_legacy_fields" class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            {!! Form::label('gateway_api_key', 'Legacy API key (other providers; leave blank to keep current)') !!}
                            {!! Form::password('gateway_api_key', ['class' => 'form-control', 'autocomplete' => 'new-password']) !!}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Newsletter: Mailchimp / MailerLite / AWeber --}}
            <div class="tab-pane" id="tab_newsletter">
                @php $newsletter = $settings['newsletter'] ?? []; @endphp
                <h4>Footer newsletter signup</h4>
                <p class="help-block">
                    When enabled with a configured provider, the storefront footer shows an email subscribe form.
                    Secrets are encrypted at rest; leave secret fields blank to keep the current value.
                </p>
                <div class="checkbox">
                    <label>
                        {!! Form::checkbox('newsletter_enabled', 1, $newsletter['enabled'] ?? false) !!} Enable newsletter signup
                    </label>
                </div>
                <div class="checkbox">
                    <label>
                        {!! Form::checkbox('newsletter_double_opt_in', 1, $newsletter['double_opt_in'] ?? true) !!}
                        Prefer double opt-in (confirmation email) when the provider supports it
                    </label>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('newsletter_provider', 'Provider') !!}
                            {!! Form::select('newsletter_provider', [
                                '' => '— Select —',
                                'mailchimp' => 'Mailchimp',
                                'mailerlite' => 'MailerLite',
                                'aweber' => 'AWeber',
                            ], $newsletter['provider'] ?? '', ['class' => 'form-control', 'id' => 'newsletter_provider']) !!}
                        </div>
                    </div>
                </div>

                <div id="newsletter_mailchimp_fields" class="newsletter-provider-fields">
                    <h4>Mailchimp</h4>
                    @php $mc = $newsletter['mailchimp'] ?? []; @endphp
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                {!! Form::label('newsletter_mailchimp_api_key', 'API key (leave blank to keep current)') !!}
                                {!! Form::password('newsletter_mailchimp_api_key', ['class' => 'form-control', 'autocomplete' => 'new-password']) !!}
                                <p class="help-block">Format: <code>xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-usX</code></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                {!! Form::label('newsletter_mailchimp_audience_id', 'Audience / list ID') !!}
                                {!! Form::text('newsletter_mailchimp_audience_id', $mc['audience_id'] ?? '', ['class' => 'form-control']) !!}
                            </div>
                        </div>
                    </div>
                </div>

                <div id="newsletter_mailerlite_fields" class="newsletter-provider-fields">
                    <h4>MailerLite</h4>
                    @php $ml = $newsletter['mailerlite'] ?? []; @endphp
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                {!! Form::label('newsletter_mailerlite_api_token', 'API token (leave blank to keep current)') !!}
                                {!! Form::password('newsletter_mailerlite_api_token', ['class' => 'form-control', 'autocomplete' => 'new-password']) !!}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                {!! Form::label('newsletter_mailerlite_group_id', 'Group ID (optional)') !!}
                                {!! Form::text('newsletter_mailerlite_group_id', $ml['group_id'] ?? '', ['class' => 'form-control']) !!}
                            </div>
                        </div>
                    </div>
                </div>

                <div id="newsletter_aweber_fields" class="newsletter-provider-fields">
                    <h4>AWeber</h4>
                    <p class="help-block">Create an AWeber developer app and paste a valid OAuth access token. Double opt-in is also controlled in the AWeber list settings.</p>
                    @php $aw = $newsletter['aweber'] ?? []; @endphp
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                {!! Form::label('newsletter_aweber_access_token', 'Access token (leave blank to keep current)') !!}
                                {!! Form::password('newsletter_aweber_access_token', ['class' => 'form-control', 'autocomplete' => 'new-password']) !!}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                {!! Form::label('newsletter_aweber_account_id', 'Account ID') !!}
                                {!! Form::text('newsletter_aweber_account_id', $aw['account_id'] ?? '', ['class' => 'form-control']) !!}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                {!! Form::label('newsletter_aweber_list_id', 'List ID') !!}
                                {!! Form::text('newsletter_aweber_list_id', $aw['list_id'] ?? '', ['class' => 'form-control']) !!}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Security: Turnstile --}}
            <div class="tab-pane" id="tab_security">
                <h4>Spam protection (Cloudflare Turnstile)</h4>
                <p class="help-block">When both site key and secret key are saved, the contact, registration, and newsletter forms require Turnstile verification.</p>
                @php $turnstile = $settings['turnstile'] ?? []; @endphp
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            {!! Form::label('turnstile_site_key', 'Site key') !!}
                            {!! Form::text('turnstile_site_key', $turnstile['site_key'] ?? '', ['class' => 'form-control', 'autocomplete' => 'off']) !!}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            {!! Form::label('turnstile_secret_key', 'Secret key (leave blank to keep current)') !!}
                            {!! Form::password('turnstile_secret_key', ['class' => 'form-control', 'autocomplete' => 'new-password']) !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="box-footer" style="border-top: 1px solid #f4f4f4; padding: 10px 15px;">
            <button type="submit" class="btn btn-primary pull-right">Save settings</button>
        </div>
    </div>

    {!! Form::close() !!}
</section>
@endsection

@section('javascript')
<script type="text/javascript">
    $(document).ready(function () {
        $('.select2').select2();

        // Open the tab that matches a deep-link hash (e.g. #payment-icons-settings).
        function activateTabFromHash() {
            var hash = window.location.hash;
            if (!hash) {
                return;
            }
            var $target = $(hash);
            if (!$target.length) {
                return;
            }
            var $pane = $target.closest('.tab-pane');
            if (!$pane.length) {
                return;
            }
            $('a[href="#' + $pane.attr('id') + '"]', '#storefront_settings_tabs').tab('show');
        }
        activateTabFromHash();
        $(window).on('hashchange', activateTabFromHash);

        // Keep the accent color text field and native color picker in sync.
        var accentText = $('#theme_accent_color');
        var accentPicker = $('#theme_accent_color_picker');

        accentPicker.on('input change', function () {
            accentText.val($(this).val());
        });

        accentText.on('input change', function () {
            var val = $(this).val();
            if (/^#([0-9a-fA-F]{6})$/.test(val)) {
                accentPicker.val(val);
            }
        });

        function toggleGatewayFields() {
            var provider = $('#gateway_provider').val();
            $('#gateway_fawry_fields').toggle(provider === 'fawry');
            $('#gateway_legacy_fields').toggle(provider !== 'fawry' && provider !== '');
        }

        $('#gateway_provider').on('change', toggleGatewayFields);
        toggleGatewayFields();

        function toggleNewsletterFields() {
            var provider = $('#newsletter_provider').val();
            $('.newsletter-provider-fields').hide();
            if (provider) {
                $('#newsletter_' + provider + '_fields').show();
            }
        }
        $('#newsletter_provider').on('change', toggleNewsletterFields);
        toggleNewsletterFields();

        // Footer payment icons — add / remove rows
        var paymentIconIndex = $('#payment_icons_tbody .payment-icon-row').length;

        function paymentIconRowHtml(index) {
            return '' +
                '<tr class="payment-icon-row" data-index="' + index + '">' +
                '<td>' +
                '<input type="text" class="form-control" name="payment_icons[' + index + '][label]" value="" maxlength="80" placeholder="e.g. Visa">' +
                '<input type="hidden" name="payment_icons[' + index + '][existing_image]" value="">' +
                '</td>' +
                '<td>' +
                '<input type="text" class="form-control payment-icon-url" name="payment_icons[' + index + '][url]" value="" maxlength="500" placeholder="https://…">' +
                '</td>' +
                '<td>' +
                '<input type="file" class="form-control payment-icon-file" name="payment_icon_image_' + index + '" accept="image/*">' +
                '</td>' +
                '<td class="text-center"><span class="text-muted">—</span></td>' +
                '<td class="text-center">' +
                '<button type="button" class="btn btn-danger btn-xs remove-payment-icon" title="Remove">&times;</button>' +
                '</td>' +
                '</tr>';
        }

        $('#add_payment_icon_row').on('click', function () {
            if ($('#payment_icons_tbody .payment-icon-row').length >= 20) {
                toastr.warning('Maximum 20 payment icons.');
                return;
            }
            $('#payment_icons_tbody').append(paymentIconRowHtml(paymentIconIndex));
            paymentIconIndex += 1;
        });

        $('#payment_icons_tbody').on('click', '.remove-payment-icon', function () {
            $(this).closest('tr').remove();
        });

        // Promotional banners — add / remove rows
        var bannerIndex = $('#banners_tbody .banner-row').length;

        function bannerRowHtml(index) {
            return '' +
                '<tr class="banner-row" data-index="' + index + '">' +
                '<td>' +
                '<input type="hidden" name="banners[' + index + '][id]" value="">' +
                '<select class="form-control banner-placement" name="banners[' + index + '][placement]">' +
                '<option value="home" selected>Home</option>' +
                '<option value="category">Category</option>' +
                '</select>' +
                '</td>' +
                '<td><input type="text" class="form-control banner-category-slug" name="banners[' + index + '][category_slug]" value="" maxlength="191" placeholder="e.g. playstation" disabled></td>' +
                '<td>' +
                '<input type="text" class="form-control" name="banners[' + index + '][title_en]" value="" maxlength="120" placeholder="EN alt/title">' +
                '<input type="text" class="form-control" name="banners[' + index + '][title_ar]" value="" maxlength="120" placeholder="AR" dir="rtl" style="margin-top:4px;">' +
                '</td>' +
                '<td><input type="text" class="form-control" name="banners[' + index + '][link]" value="" maxlength="500" placeholder="/products or https://…"></td>' +
                '<td>' +
                '<input type="text" class="form-control" name="banners[' + index + '][url]" value="" maxlength="500" placeholder="https://…">' +
                '<input type="hidden" name="banners[' + index + '][existing_image]" value="">' +
                '</td>' +
                '<td><input type="file" class="form-control" name="banner_image_' + index + '" accept="image/*"></td>' +
                '<td><input type="number" class="form-control" name="banners[' + index + '][sort_order]" value="' + index + '" min="0" max="999"></td>' +
                '<td class="text-center"><input type="checkbox" name="banners[' + index + '][enabled]" value="1" checked></td>' +
                '<td class="text-center"><span class="text-muted">—</span></td>' +
                '<td class="text-center"><button type="button" class="btn btn-danger btn-xs remove-banner" title="Remove">&times;</button></td>' +
                '</tr>';
        }

        function syncBannerCategorySlug($row) {
            var isCategory = $row.find('.banner-placement').val() === 'category';
            $row.find('.banner-category-slug').prop('disabled', !isCategory);
        }

        $('#add_banner_row').on('click', function () {
            if ($('#banners_tbody .banner-row').length >= 12) {
                toastr.warning('Maximum 12 banners.');
                return;
            }
            $('#banners_tbody').append(bannerRowHtml(bannerIndex));
            bannerIndex += 1;
        });

        $('#banners_tbody').on('click', '.remove-banner', function () {
            $(this).closest('tr').remove();
        });

        $('#banners_tbody').on('change', '.banner-placement', function () {
            syncBannerCategorySlug($(this).closest('tr'));
        });

        // ---- Shipping zones manager ----
        var zonesUrl = @json(action([\App\Http\Controllers\StorefrontShippingZoneController::class, 'index']));
        var csrfToken = $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}';

        function renderZones(payload) {
            var zones = (payload && payload.zones) ? payload.zones : [];
            if (!zones.length) {
                $('#storefront_shipping_zones_app').html('<p class="text-muted">No zones yet.</p>');
                return;
            }
            var html = '<div class="table-responsive"><table class="table table-bordered table-condensed"><thead><tr>' +
                '<th>Priority</th><th>Name</th><th>Locations</th><th>Methods</th><th></th></tr></thead><tbody>';
            zones.forEach(function (zone) {
                var locs = (zone.locations || []).map(function (l) { return l.type + ':' + l.code; }).join(', ') || (zone.is_catch_all ? 'Catch-all' : '—');
                var methods = (zone.methods || []).map(function (m) {
                    return m.type + ' — ' + m.title + (m.is_enabled ? '' : ' (off)');
                }).join('<br>') || '—';
                html += '<tr data-zone-id="' + zone.id + '">' +
                    '<td>' + zone.priority + '</td>' +
                    '<td>' + $('<div>').text(zone.name).html() + (zone.is_enabled ? '' : ' <em>(disabled)</em>') + '</td>' +
                    '<td><small>' + $('<div>').text(locs).html() + '</small></td>' +
                    '<td><small>' + methods + '</small></td>' +
                    '<td class="text-nowrap">' +
                    '<button type="button" class="btn btn-xs btn-default sf-add-method" data-zone-id="' + zone.id + '">+ Method</button> ' +
                    '<button type="button" class="btn btn-xs btn-danger sf-del-zone" data-zone-id="' + zone.id + '">Delete</button>' +
                    '</td></tr>';
            });
            html += '</tbody></table></div>';
            $('#storefront_shipping_zones_app').html(html);
        }

        function loadZones() {
            $('#storefront_shipping_zones_app').html('<p class="text-muted">Loading zones…</p>');
            $.getJSON(zonesUrl).done(function (res) {
                renderZones(res.data || {});
            }).fail(function () {
                $('#storefront_shipping_zones_app').html('<p class="text-danger">Could not load zones.</p>');
            });
        }

        $('#sf_shipping_reload_zones').on('click', loadZones);
        loadZones();

        $('#sf_shipping_add_zone').on('click', function () {
            var name = prompt('Zone name (e.g. Greater Cairo)');
            if (!name) return;
            var code = prompt('Country code (e.g. EG) — leave empty for catch-all', 'EG');
            var states = code
                ? prompt('Governorate codes comma-separated (optional, e.g. C,GZ). Empty = whole country.', '')
                : '';
            var locations = [];
            if (code) {
                locations.push({ type: 'country', code: code });
                if (states) {
                    states.split(',').forEach(function (s) {
                        s = (s || '').trim();
                        if (s) locations.push({ type: 'state', code: s });
                    });
                }
            }
            var payload = {
                name: name,
                priority: 50,
                is_catch_all: !code,
                is_enabled: true,
                locations: locations
            };
            $.ajax({
                url: @json(action([\App\Http\Controllers\StorefrontShippingZoneController::class, 'store'])),
                method: 'POST',
                data: JSON.stringify(payload),
                contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': csrfToken },
            }).done(loadZones).fail(function () { toastr.error('Could not create zone'); });
        });

        $('#storefront_shipping_zones_app').on('click', '.sf-del-zone', function () {
            if (!confirm('Delete this zone and its methods?')) return;
            var id = $(this).data('zone-id');
            $.ajax({
                url: '/storefront/shipping/zones/' + id,
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken },
            }).done(loadZones);
        });

        $('#storefront_shipping_zones_app').on('click', '.sf-add-method', function () {
            var zoneId = $(this).data('zone-id');
            var type = prompt('Method type: flat_rate, free_shipping, or local_pickup', 'flat_rate');
            if (!type) return;
            var title = prompt('Title', 'Standard delivery');
            if (!title) return;
            var cost = type === 'flat_rate' ? parseFloat(prompt('Cost', '50') || '0') : 0;
            var perKg = type === 'flat_rate' ? parseFloat(prompt('Extra cost per kg (0 to skip)', '0') || '0') : 0;
            var minAmount = type === 'free_shipping' ? parseFloat(prompt('Min order for free shipping', '1500') || '0') : 0;
            var settings = type === 'flat_rate'
                ? { cost: cost, cost_per_item: 0, cost_per_kg: perKg, eta_min_days: 2, eta_max_days: 5, class_costs: {} }
                : (type === 'free_shipping'
                    ? { requires: 'min_amount', min_amount: minAmount }
                    : { cost: 0, location_ids: [] });
            $.ajax({
                url: '/storefront/shipping/zones/' + zoneId + '/methods',
                method: 'POST',
                data: JSON.stringify({ type: type, title: title, settings: settings }),
                contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': csrfToken },
            }).done(loadZones).fail(function () { toastr.error('Could not add method'); });
        });

        // Shipping classes
        var classesUrl = @json(action([\App\Http\Controllers\StorefrontShippingZoneController::class, 'classesIndex']));
        function renderClasses(payload) {
            var classes = (payload && payload.classes) ? payload.classes : [];
            if (!classes.length) {
                $('#storefront_shipping_classes_app').html('<p class="text-muted">No shipping classes yet.</p>');
                return;
            }
            var html = '<ul class="list-unstyled">';
            classes.forEach(function (c) {
                html += '<li style="margin-bottom:4px;">' +
                    $('<div>').text(c.name + (c.slug ? ' (' + c.slug + ')' : '')).html() +
                    ' <button type="button" class="btn btn-xs btn-danger sf-del-class" data-id="' + c.id + '">Delete</button></li>';
            });
            html += '</ul>';
            $('#storefront_shipping_classes_app').html(html);
        }
        function loadClasses() {
            $.getJSON(classesUrl).done(function (res) {
                renderClasses(res.data || {});
            });
        }
        loadClasses();
        $('#sf_shipping_add_class').on('click', function () {
            var name = prompt('Shipping class name (e.g. Bulky)');
            if (!name) return;
            $.ajax({
                url: @json(action([\App\Http\Controllers\StorefrontShippingZoneController::class, 'storeClass'])),
                method: 'POST',
                data: JSON.stringify({ name: name }),
                contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': csrfToken },
            }).done(loadClasses).fail(function () { toastr.error('Could not create class'); });
        });
        $('#storefront_shipping_classes_app').on('click', '.sf-del-class', function () {
            if (!confirm('Delete this shipping class?')) return;
            var id = $(this).data('id');
            $.ajax({
                url: '/storefront/shipping/classes/' + id,
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken },
            }).done(loadClasses);
        });
    });
</script>
@endsection
