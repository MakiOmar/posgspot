@extends('layouts.app')
@section('title', 'Storefront Settings')

@section('content')
<!-- Storefront settings — public shop configuration -->
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

    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Public website configuration</h3>
        </div>

        {!! Form::open(['url' => action([\App\Http\Controllers\StorefrontSettingController::class, 'update']), 'method' => 'post', 'id' => 'storefront_settings_form']) !!}
        <div class="box-body">
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
            <h4>Shipping</h4>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('shipping_flat_rate', 'Flat shipping rate') !!}
                        {!! Form::number('shipping_flat_rate', $settings['shipping']['flat_rate'] ?? 0, ['class' => 'form-control', 'step' => '0.01', 'min' => '0']) !!}
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('shipping_free_threshold', 'Free shipping above') !!}
                        {!! Form::number('shipping_free_threshold', $settings['shipping']['free_shipping_threshold'] ?? 0, ['class' => 'form-control', 'step' => '0.01', 'min' => '0']) !!}
                    </div>
                </div>
            </div>

            <hr>
            <h4>Contact &amp; social</h4>
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

            <hr>
            <h4>Announcement bar</h4>
            <div class="checkbox">
                <label>
                    {!! Form::checkbox('announcement_enabled', 1, $settings['announcement']['enabled'] ?? false) !!} Show announcement bar
                </label>
            </div>
            <div class="form-group">
                {!! Form::label('announcement_message', 'Message') !!}
                {!! Form::text('announcement_message', $settings['announcement']['message'] ?? '', ['class' => 'form-control']) !!}
            </div>
            <div class="form-group">
                {!! Form::label('announcement_link', 'Link (optional)') !!}
                {!! Form::text('announcement_link', $settings['announcement']['link'] ?? '', ['class' => 'form-control']) !!}
            </div>

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
                        {!! Form::select('gateway_provider', ['' => '—', 'myfatoorah' => 'MyFatoorah', 'paymob' => 'Paymob'], $settings['gateway']['provider'] ?? '', ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="form-group">
                        {!! Form::label('gateway_api_key', 'API key (leave blank to keep current)') !!}
                        {!! Form::password('gateway_api_key', ['class' => 'form-control', 'autocomplete' => 'new-password']) !!}
                    </div>
                </div>
            </div>
        </div>

        <div class="box-footer">
            <button type="submit" class="btn btn-primary pull-right">Save settings</button>
        </div>
        {!! Form::close() !!}
    </div>
</section>
@endsection

@section('javascript')
<script type="text/javascript">
    $(document).ready(function () {
        $('.select2').select2();
    });
</script>
@endsection
