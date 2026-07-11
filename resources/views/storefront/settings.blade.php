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
                    Checkout matches the customer’s <strong>country + governorate</strong> (not city) to the first zone by priority.
                    Example: create “Greater Cairo” with selected governorates (Cairo, Giza, …) at priority 10,
                    then keep “Egypt” (whole country, no governorates) or “Everywhere else” for the rest.
                    Leave governorates empty on a country zone to cover the whole country.
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

                {{-- Edit zone modal --}}
                <div class="modal fade" id="sf_zone_modal" tabindex="-1" role="dialog">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                <h4 class="modal-title" id="sf_zone_modal_title">Edit zone</h4>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" id="sf_zone_id" value="">
                                <div class="form-group">
                                    <label for="sf_zone_name">Name</label>
                                    <input type="text" class="form-control" id="sf_zone_name" maxlength="191">
                                </div>
                                <div class="row">
                                    <div class="col-sm-4">
                                        <div class="form-group">
                                            <label for="sf_zone_priority">Priority (lower = first match)</label>
                                            <input type="number" class="form-control" id="sf_zone_priority" min="0" value="50">
                                        </div>
                                    </div>
                                    <div class="col-sm-4">
                                        <div class="checkbox" style="margin-top: 28px;">
                                            <label><input type="checkbox" id="sf_zone_enabled" checked> Enabled</label>
                                        </div>
                                    </div>
                                    <div class="col-sm-4">
                                        <div class="checkbox" style="margin-top: 28px;">
                                            <label><input type="checkbox" id="sf_zone_catch_all"> Catch-all (rest of world)</label>
                                        </div>
                                    </div>
                                </div>
                                <div id="sf_zone_locations_wrap">
                                    <div class="form-group">
                                        <label for="sf_zone_country">Country</label>
                                        <input type="text" class="form-control" id="sf_zone_country" value="EG" maxlength="8" placeholder="EG">
                                    </div>
                                    <div class="form-group">
                                        <label for="sf_zone_states">Governorates (Egypt)</label>
                                        <select id="sf_zone_states" class="form-control select2" multiple style="width:100%;" data-placeholder="Search governorates…"></select>
                                        <p class="help-block">Search and multi-select. Empty = whole country. City-level matching is not used.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" id="sf_zone_save">Save zone</button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Edit method modal --}}
                <div class="modal fade" id="sf_method_modal" tabindex="-1" role="dialog">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                <h4 class="modal-title" id="sf_method_modal_title">Edit method</h4>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" id="sf_method_id" value="">
                                <input type="hidden" id="sf_method_zone_id" value="">
                                <div class="form-group" id="sf_method_type_wrap">
                                    <label for="sf_method_type">Type</label>
                                    <select id="sf_method_type" class="form-control">
                                        <option value="flat_rate">Flat rate</option>
                                        <option value="free_shipping">Free shipping</option>
                                        <option value="local_pickup">Local pickup</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="sf_method_title_en">Title (EN)</label>
                                    <input type="text" class="form-control" id="sf_method_title_en" maxlength="191">
                                </div>
                                <div class="form-group">
                                    <label for="sf_method_title_ar">Title (AR)</label>
                                    <input type="text" class="form-control" id="sf_method_title_ar" maxlength="191" dir="rtl">
                                </div>
                                <div class="checkbox">
                                    <label><input type="checkbox" id="sf_method_enabled" checked> Enabled</label>
                                </div>
                                <div id="sf_method_flat_fields">
                                    <div class="row">
                                        <div class="col-sm-4">
                                            <div class="form-group">
                                                <label for="sf_method_cost">Cost</label>
                                                <input type="number" step="0.01" min="0" class="form-control" id="sf_method_cost" value="0">
                                            </div>
                                        </div>
                                        <div class="col-sm-4">
                                            <div class="form-group">
                                                <label for="sf_method_eta_min">ETA min days</label>
                                                <input type="number" min="0" class="form-control" id="sf_method_eta_min" value="2">
                                            </div>
                                        </div>
                                        <div class="col-sm-4">
                                            <div class="form-group">
                                                <label for="sf_method_eta_max">ETA max days</label>
                                                <input type="number" min="0" class="form-control" id="sf_method_eta_max" value="5">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="sf_method_per_kg">Extra per kg (optional)</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="sf_method_per_kg" value="0">
                                    </div>
                                </div>
                                <div id="sf_method_free_fields" style="display:none;">
                                    <div class="form-group">
                                        <label for="sf_method_min_amount">Min order amount for free shipping</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="sf_method_min_amount" value="1500">
                                    </div>
                                </div>
                                <div id="sf_method_pickup_fields" style="display:none;">
                                    <p class="help-block">Pickup uses locations with “Enable pickup”. Leave empty to allow all pickup-enabled branches.</p>
                                    <div class="form-group">
                                        <label for="sf_method_pickup_locations">Pickup locations (optional)</label>
                                        <select id="sf_method_pickup_locations" class="form-control select2" multiple style="width:100%;" data-placeholder="Search locations…"></select>
                                    </div>
                                    <div class="form-group">
                                        <label for="sf_method_pickup_cost">Pickup fee</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="sf_method_pickup_cost" value="0">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" id="sf_method_save">Save method</button>
                            </div>
                        </div>
                    </div>
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
                                {!! Form::checkbox('courier_bosta_staging', 1, $settings['couriers']['bosta']['staging'] ?? false) !!} Bosta staging (optional; production is default)
                            </label>
                            <p class="help-block">Official WooCommerce plugin uses production only. Enable staging only when testing against Bosta’s staging API.</p>
                        </div>
                    </div>
                </div>

                <hr>
                <h4>Digital catalog (games accounts / gift cards)</h4>
                <p class="help-block">Maps Accounts catalog lines to POS products. Secrets allocate only after payment.</p>
                <div class="row">
                    <div class="col-md-4">
                        <div class="checkbox">
                            <label>
                                {!! Form::checkbox('digital_enabled', 1, $settings['digital']['enabled'] ?? true) !!} Enable digital catalog
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('digital_accounts_store_profile_id', 'Accounts store profile ID') !!}
                            {!! Form::number('digital_accounts_store_profile_id', $settings['digital']['accounts_store_profile_id'] ?? 17, ['class' => 'form-control', 'min' => 1]) !!}
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('digital_primary_product_id', 'Primary account POS product ID') !!}
                            {!! Form::number('digital_primary_product_id', $settings['digital']['primary_product_id'] ?? null, ['class' => 'form-control', 'min' => 1]) !!}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('digital_secondary_product_id', 'Secondary account POS product ID') !!}
                            {!! Form::number('digital_secondary_product_id', $settings['digital']['secondary_product_id'] ?? null, ['class' => 'form-control', 'min' => 1]) !!}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('digital_gift_card_product_id', 'Gift card POS product ID') !!}
                            {!! Form::number('digital_gift_card_product_id', $settings['digital']['gift_card_product_id'] ?? null, ['class' => 'form-control', 'min' => 1]) !!}
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
        var sfZonesCache = [];
        var sfEgyptStates = [];
        var sfPickupLocations = [];

        function escapeHtml(str) {
            return $('<div>').text(str == null ? '' : String(str)).html();
        }

        function fillEgyptStatesSelect($select, selectedCodes) {
            selectedCodes = selectedCodes || [];
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }
            var html = '';
            sfEgyptStates.forEach(function (st) {
                var sel = selectedCodes.indexOf(String(st.code)) !== -1 ? ' selected' : '';
                html += '<option value="' + escapeHtml(st.code) + '"' + sel + '>' + escapeHtml(st.name) + ' (' + escapeHtml(st.code) + ')</option>';
            });
            $select.html(html);
            $select.select2({
                width: '100%',
                placeholder: $select.data('placeholder') || 'Search…',
                allowClear: true
            });
        }

        function fillPickupLocationsSelect($select, selectedIds) {
            selectedIds = (selectedIds || []).map(Number);
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }
            var html = '';
            sfPickupLocations.forEach(function (loc) {
                var sel = selectedIds.indexOf(Number(loc.id)) !== -1 ? ' selected' : '';
                html += '<option value="' + loc.id + '"' + sel + '>' + escapeHtml(loc.name) + '</option>';
            });
            $select.html(html);
            $select.select2({
                width: '100%',
                placeholder: $select.data('placeholder') || 'Search…',
                allowClear: true
            });
        }

        function locationLabel(zone) {
            if (zone.is_catch_all) return 'Catch-all';
            var locs = zone.locations || [];
            var states = locs.filter(function (l) { return l.type === 'state'; }).map(function (l) { return l.code; });
            var countries = locs.filter(function (l) { return l.type === 'country'; }).map(function (l) { return l.code; });
            if (states.length) {
                var names = states.map(function (code) {
                    var found = sfEgyptStates.find(function (s) { return String(s.code) === String(code); });
                    return found ? found.name : code;
                });
                return (countries[0] || 'EG') + ': ' + names.join(', ');
            }
            if (countries.length) return countries.join(', ') + ' (whole country)';
            return '—';
        }

        function renderZones(payload) {
            sfZonesCache = (payload && payload.zones) ? payload.zones : [];
            sfEgyptStates = (payload && payload.egypt_states) ? payload.egypt_states : sfEgyptStates;
            sfPickupLocations = (payload && payload.pickup_locations) ? payload.pickup_locations : sfPickupLocations;

            if (!sfZonesCache.length) {
                $('#storefront_shipping_zones_app').html('<p class="text-muted">No zones yet.</p>');
                return;
            }
            var html = '<div class="table-responsive"><table class="table table-bordered table-condensed"><thead><tr>' +
                '<th>Priority</th><th>Name</th><th>Locations</th><th>Methods</th><th></th></tr></thead><tbody>';
            sfZonesCache.forEach(function (zone) {
                var methodsHtml = (zone.methods || []).map(function (m) {
                    return '<div style="margin-bottom:4px;">' +
                        escapeHtml(m.type) + ' — ' + escapeHtml(m.title) + (m.is_enabled ? '' : ' <em>(off)</em>') +
                        ' <button type="button" class="btn btn-xs btn-default sf-edit-method" data-method-id="' + m.id + '" data-zone-id="' + zone.id + '">Edit</button>' +
                        ' <button type="button" class="btn btn-xs btn-danger sf-del-method" data-method-id="' + m.id + '">×</button>' +
                        '</div>';
                }).join('') || '—';
                html += '<tr data-zone-id="' + zone.id + '">' +
                    '<td>' + zone.priority + '</td>' +
                    '<td>' + escapeHtml(zone.name) + (zone.is_enabled ? '' : ' <em>(disabled)</em>') + '</td>' +
                    '<td><small>' + escapeHtml(locationLabel(zone)) + '</small></td>' +
                    '<td><small>' + methodsHtml + '</small></td>' +
                    '<td class="text-nowrap">' +
                    '<button type="button" class="btn btn-xs btn-primary sf-edit-zone" data-zone-id="' + zone.id + '">Edit zone</button> ' +
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

        function toggleCatchAllUi() {
            var catchAll = $('#sf_zone_catch_all').is(':checked');
            $('#sf_zone_locations_wrap').toggle(!catchAll);
        }

        function openZoneModal(zone) {
            if (zone) {
                $('#sf_zone_modal_title').text('Edit zone');
                $('#sf_zone_id').val(zone.id);
                $('#sf_zone_name').val(zone.name);
                $('#sf_zone_priority').val(zone.priority);
                $('#sf_zone_enabled').prop('checked', !!zone.is_enabled);
                $('#sf_zone_catch_all').prop('checked', !!zone.is_catch_all);
                var country = 'EG';
                var stateCodes = [];
                (zone.locations || []).forEach(function (l) {
                    if (l.type === 'country') country = l.code;
                    if (l.type === 'state') stateCodes.push(String(l.code));
                });
                $('#sf_zone_country').val(country);
                fillEgyptStatesSelect($('#sf_zone_states'), stateCodes);
            } else {
                $('#sf_zone_modal_title').text('Add zone');
                $('#sf_zone_id').val('');
                $('#sf_zone_name').val('');
                $('#sf_zone_priority').val(50);
                $('#sf_zone_enabled').prop('checked', true);
                $('#sf_zone_catch_all').prop('checked', false);
                $('#sf_zone_country').val('EG');
                fillEgyptStatesSelect($('#sf_zone_states'), []);
            }
            toggleCatchAllUi();
            $('#sf_zone_modal').modal('show');
        }

        function syncMethodTypeFields() {
            var type = $('#sf_method_type').val();
            $('#sf_method_flat_fields').toggle(type === 'flat_rate');
            $('#sf_method_free_fields').toggle(type === 'free_shipping');
            $('#sf_method_pickup_fields').toggle(type === 'local_pickup');
        }

        function openMethodModal(zoneId, method) {
            $('#sf_method_zone_id').val(zoneId);
            fillPickupLocationsSelect($('#sf_method_pickup_locations'), []);
            if (method) {
                $('#sf_method_modal_title').text('Edit method');
                $('#sf_method_id').val(method.id);
                $('#sf_method_type_wrap').hide();
                $('#sf_method_type').val(method.type);
                var i18n = method.title_i18n || {};
                $('#sf_method_title_en').val(i18n.en || method.title || '');
                $('#sf_method_title_ar').val(i18n.ar || '');
                $('#sf_method_enabled').prop('checked', !!method.is_enabled);
                var s = method.settings || {};
                $('#sf_method_cost').val(s.cost != null ? s.cost : 0);
                $('#sf_method_eta_min').val(s.eta_min_days != null ? s.eta_min_days : 2);
                $('#sf_method_eta_max').val(s.eta_max_days != null ? s.eta_max_days : 5);
                $('#sf_method_per_kg').val(s.cost_per_kg != null ? s.cost_per_kg : 0);
                $('#sf_method_min_amount').val(s.min_amount != null ? s.min_amount : 1500);
                $('#sf_method_pickup_cost').val(s.cost != null ? s.cost : 0);
                fillPickupLocationsSelect($('#sf_method_pickup_locations'), s.location_ids || []);
            } else {
                $('#sf_method_modal_title').text('Add method');
                $('#sf_method_id').val('');
                $('#sf_method_type_wrap').show();
                $('#sf_method_type').val('flat_rate');
                $('#sf_method_title_en').val('Standard delivery');
                $('#sf_method_title_ar').val('');
                $('#sf_method_enabled').prop('checked', true);
                $('#sf_method_cost').val(50);
                $('#sf_method_eta_min').val(2);
                $('#sf_method_eta_max').val(5);
                $('#sf_method_per_kg').val(0);
                $('#sf_method_min_amount').val(1500);
                $('#sf_method_pickup_cost').val(0);
            }
            syncMethodTypeFields();
            $('#sf_method_modal').modal('show');
        }

        function buildMethodSettings(type) {
            if (type === 'flat_rate') {
                return {
                    cost: parseFloat($('#sf_method_cost').val() || '0'),
                    cost_per_item: 0,
                    cost_per_kg: parseFloat($('#sf_method_per_kg').val() || '0'),
                    eta_min_days: parseInt($('#sf_method_eta_min').val() || '0', 10),
                    eta_max_days: parseInt($('#sf_method_eta_max').val() || '0', 10),
                    class_costs: {}
                };
            }
            if (type === 'free_shipping') {
                return {
                    requires: 'min_amount',
                    min_amount: parseFloat($('#sf_method_min_amount').val() || '0')
                };
            }
            var ids = ($('#sf_method_pickup_locations').val() || []).map(function (v) { return parseInt(v, 10); });
            return {
                cost: parseFloat($('#sf_method_pickup_cost').val() || '0'),
                location_ids: ids
            };
        }

        $('#sf_shipping_reload_zones').on('click', loadZones);
        loadZones();

        $('#sf_zone_catch_all').on('change', toggleCatchAllUi);
        $('#sf_method_type').on('change', syncMethodTypeFields);

        $('#sf_shipping_add_zone').on('click', function () {
            openZoneModal(null);
        });

        $('#storefront_shipping_zones_app').on('click', '.sf-edit-zone', function () {
            var id = Number($(this).data('zone-id'));
            var zone = sfZonesCache.find(function (z) { return Number(z.id) === id; });
            if (zone) openZoneModal(zone);
        });

        $('#sf_zone_save').on('click', function () {
            var id = $('#sf_zone_id').val();
            var catchAll = $('#sf_zone_catch_all').is(':checked');
            var locations = [];
            if (!catchAll) {
                var country = ($('#sf_zone_country').val() || 'EG').trim().toUpperCase();
                locations.push({ type: 'country', code: country });
                var states = $('#sf_zone_states').val() || [];
                states.forEach(function (code) {
                    locations.push({ type: 'state', code: code });
                });
            }
            var payload = {
                name: ($('#sf_zone_name').val() || '').trim(),
                priority: parseInt($('#sf_zone_priority').val() || '50', 10),
                is_enabled: $('#sf_zone_enabled').is(':checked'),
                is_catch_all: catchAll,
                locations: locations
            };
            if (!payload.name) {
                toastr.error('Zone name is required');
                return;
            }
            var req = id
                ? { url: '/storefront/shipping/zones/' + id, method: 'PUT' }
                : { url: @json(action([\App\Http\Controllers\StorefrontShippingZoneController::class, 'store'])), method: 'POST' };
            $.ajax({
                url: req.url,
                method: req.method,
                data: JSON.stringify(payload),
                contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': csrfToken },
            }).done(function () {
                $('#sf_zone_modal').modal('hide');
                loadZones();
                toastr.success('Zone saved');
            }).fail(function () { toastr.error('Could not save zone'); });
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
            openMethodModal($(this).data('zone-id'), null);
        });

        $('#storefront_shipping_zones_app').on('click', '.sf-edit-method', function () {
            var zoneId = Number($(this).data('zone-id'));
            var methodId = Number($(this).data('method-id'));
            var zone = sfZonesCache.find(function (z) { return Number(z.id) === zoneId; });
            var method = zone && (zone.methods || []).find(function (m) { return Number(m.id) === methodId; });
            if (method) openMethodModal(zoneId, method);
        });

        $('#storefront_shipping_zones_app').on('click', '.sf-del-method', function () {
            if (!confirm('Delete this shipping method?')) return;
            var id = $(this).data('method-id');
            $.ajax({
                url: '/storefront/shipping/methods/' + id,
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken },
            }).done(loadZones);
        });

        $('#sf_method_save').on('click', function () {
            var methodId = $('#sf_method_id').val();
            var zoneId = $('#sf_method_zone_id').val();
            var type = $('#sf_method_type').val();
            var titleEn = ($('#sf_method_title_en').val() || '').trim();
            var titleAr = ($('#sf_method_title_ar').val() || '').trim();
            if (!titleEn) {
                toastr.error('Title (EN) is required');
                return;
            }
            var payload = {
                title: titleEn,
                title_en: titleEn,
                title_ar: titleAr || titleEn,
                is_enabled: $('#sf_method_enabled').is(':checked'),
                settings: buildMethodSettings(methodId ? ($('#sf_method_type').val()) : type)
            };
            if (!methodId) {
                payload.type = type;
            } else {
                // Keep settings type fields based on existing type select (hidden when editing).
                payload.settings = buildMethodSettings($('#sf_method_type').val());
            }
            var req = methodId
                ? { url: '/storefront/shipping/methods/' + methodId, method: 'PUT' }
                : { url: '/storefront/shipping/zones/' + zoneId + '/methods', method: 'POST' };
            $.ajax({
                url: req.url,
                method: req.method,
                data: JSON.stringify(payload),
                contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': csrfToken },
            }).done(function () {
                $('#sf_method_modal').modal('hide');
                loadZones();
                toastr.success('Method saved');
            }).fail(function () { toastr.error('Could not save method'); });
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
                    escapeHtml(c.name + (c.slug ? ' (' + c.slug + ')' : '')) +
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
