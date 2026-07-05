@extends('layouts.app')
@section('title', $title)

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ $title }}</h1>
    </section>

    <section class="content">
        @can('storefront.settings')
            <div class="alert alert-info">
                <strong>Storefront checkout behavior</strong> (show promo field, allow multiple codes per order) is configured under
                <a href="{{ action([\App\Http\Controllers\StorefrontSettingController::class, 'edit']) }}#promo-checkout-settings">Storefront Settings → Promo codes (storefront checkout)</a>,
                not on each code. Sign-in is required for customers to apply codes online.
            </div>
        @endcan
        <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 sm:tw-p-6">
            {!! Form::open(['url' => $action, 'method' => $method, 'id' => 'coupon_form']) !!}

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('code', 'Promo code:*') !!} @show_tooltip(__('lang_v1.coupon_code_help'))
                        <div class="input-group">
                            {!! Form::text('code', old('code', $coupon->code ?? ''), [
                                'class' => 'form-control',
                                'required',
                                'id' => 'coupon_code',
                                'style' => 'text-transform: uppercase',
                                'autocomplete' => 'off',
                            ]) !!}
                            @can('coupon.create')
                                <span class="input-group-btn">
                                    <button type="button" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-primary" id="generate_coupon_code" title="Generate unique promo code">
                                        Generate
                                    </button>
                                </span>
                            @endcan
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="form-group">
                        {!! Form::label('name', __('unit.name') . ':*') !!} @show_tooltip(__('lang_v1.coupon_name_help'))
                        {!! Form::text('name', old('name', $coupon->name ?? ''), ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-group">
                        {!! Form::label('description', __('lang_v1.description')) !!} @show_tooltip(__('lang_v1.coupon_description_help'))
                        {!! Form::textarea('description', old('description', $coupon->description ?? ''), ['class' => 'form-control', 'rows' => 2]) !!}
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('type', 'Type:*') !!} @show_tooltip(__('lang_v1.coupon_type_help'))
                        {!! Form::select('type', [
                            \App\Coupon::TYPE_PERCENT_ORDER => 'Percentage off order',
                            \App\Coupon::TYPE_FIXED_ORDER => 'Fixed amount off order',
                            \App\Coupon::TYPE_FREE_SHIPPING => 'Free shipping',
                        ], old('type', $coupon->type ?? \App\Coupon::TYPE_PERCENT_ORDER), ['class' => 'form-control select2', 'required', 'id' => 'coupon_type']) !!}
                    </div>
                </div>
                <div class="col-md-4" id="discount_amount_wrap">
                    <div class="form-group">
                        {!! Form::label('discount_amount', __('sale.discount_amount') . ':*') !!} @show_tooltip(__('lang_v1.coupon_discount_amount_help'))
                        {!! Form::text('discount_amount', old('discount_amount', $coupon->discount_amount ?? ''), ['class' => 'form-control input_number', 'id' => 'discount_amount']) !!}
                    </div>
                </div>
                <div class="col-md-4" id="max_discount_wrap">
                    <div class="form-group">
                        {!! Form::label('max_discount_amount', 'Max discount cap (% only)') !!} @show_tooltip(__('lang_v1.coupon_max_discount_help'))
                        {!! Form::text('max_discount_amount', old('max_discount_amount', $coupon->max_discount_amount ?? ''), ['class' => 'form-control input_number']) !!}
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('min_order_subtotal', 'Min eligible subtotal') !!} @show_tooltip(__('lang_v1.coupon_min_subtotal_help'))
                        {!! Form::text('min_order_subtotal', old('min_order_subtotal', $coupon->min_order_subtotal ?? 0), ['class' => 'form-control input_number']) !!}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('channel', 'Channel:*') !!} @show_tooltip(__('lang_v1.coupon_channel_help'))
                        {!! Form::select('channel', [
                            \App\Coupon::CHANNEL_STOREFRONT => 'Storefront only',
                            \App\Coupon::CHANNEL_POS => 'POS only',
                            \App\Coupon::CHANNEL_BOTH => 'Storefront and POS',
                        ], old('channel', $coupon->channel ?? \App\Coupon::CHANNEL_STOREFRONT), ['class' => 'form-control select2', 'required']) !!}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('applies_to', 'Applies to:*') !!} @show_tooltip(__('lang_v1.coupon_applies_to_help'))
                        {!! Form::select('applies_to', [
                            \App\Coupon::APPLIES_ALL => 'Entire cart',
                            \App\Coupon::APPLIES_CATEGORIES => 'Selected categories',
                            \App\Coupon::APPLIES_PRODUCTS => 'Selected products',
                        ], old('applies_to', $coupon->applies_to ?? \App\Coupon::APPLIES_ALL), ['class' => 'form-control select2', 'required', 'id' => 'applies_to']) !!}
                    </div>
                </div>

                <div class="col-md-12" id="category_ids_wrap">
                    <div class="form-group">
                        {!! Form::label('category_ids', __('product.category')) !!} @show_tooltip(__('lang_v1.coupon_categories_help'))
                        {!! Form::select('category_ids[]', $categories, old('category_ids', $coupon ? $coupon->categories->pluck('id')->all() : []), ['class' => 'form-control select2', 'multiple', 'id' => 'category_ids']) !!}
                    </div>
                </div>
                <div class="col-md-12" id="variation_ids_wrap">
                    <div class="form-group">
                        {!! Form::label('variation_ids', __('report.products')) !!} @show_tooltip(__('lang_v1.coupon_products_help'))
                        {!! Form::select('variation_ids[]', $variations, old('variation_ids', $coupon ? $coupon->variations->pluck('id')->all() : []), ['class' => 'form-control select2', 'multiple', 'id' => 'variation_ids']) !!}
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('starts_at', __('lang_v1.starts_at')) !!} @show_tooltip(__('lang_v1.coupon_starts_at_help'))
                        {!! Form::text('starts_at', old('starts_at', $starts_at), ['class' => 'form-control discount_date', 'readonly']) !!}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('ends_at', __('lang_v1.ends_at')) !!} @show_tooltip(__('lang_v1.coupon_ends_at_help'))
                        {!! Form::text('ends_at', old('ends_at', $ends_at), ['class' => 'form-control discount_date', 'readonly']) !!}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('max_uses_total', 'Max total redemptions') !!} @show_tooltip(__('lang_v1.coupon_max_uses_total_help'))
                        {!! Form::number('max_uses_total', old('max_uses_total', $coupon->max_uses_total ?? ''), ['class' => 'form-control', 'min' => 0, 'placeholder' => 'Unlimited']) !!}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('max_uses_per_customer', 'Max per customer') !!} @show_tooltip(__('lang_v1.coupon_max_uses_per_customer_help'))
                        {!! Form::number('max_uses_per_customer', old('max_uses_per_customer', $coupon->max_uses_per_customer ?? ''), ['class' => 'form-control', 'min' => 0, 'placeholder' => 'Unlimited']) !!}
                    </div>
                </div>

                @if(!empty($coupon))
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Times used</label>
                            <p class="form-control-static">{{ (int) $coupon->times_used }}</p>
                        </div>
                    </div>
                @endif

                <div class="col-md-12">
                    <div class="checkbox">
                        <label>
                            {!! Form::checkbox('is_active', 1, old('is_active', $coupon->is_active ?? true), ['class' => 'input-icheck']) !!}
                            @lang('lang_v1.is_active') @show_tooltip(__('lang_v1.coupon_active_help'))
                        </label>
                    </div>
                    <div class="checkbox">
                        <label>
                            {!! Form::checkbox('first_order_only', 1, old('first_order_only', $coupon->first_order_only ?? false), ['class' => 'input-icheck']) !!}
                            First storefront order only @show_tooltip(__('lang_v1.coupon_first_order_only_help'))
                        </label>
                    </div>
                    <div class="checkbox">
                        <label>
                            {!! Form::checkbox('exclude_sale_items', 1, old('exclude_sale_items', $coupon->exclude_sale_items ?? false), ['class' => 'input-icheck']) !!}
                            Exclude sale-priced items from eligible subtotal @show_tooltip(__('lang_v1.coupon_exclude_sale_help'))
                        </label>
                    </div>
                    <div class="checkbox">
                        <label>
                            {!! Form::checkbox('stack_with_reward_points', 1, old('stack_with_reward_points', $coupon->stack_with_reward_points ?? true), ['class' => 'input-icheck']) !!}
                            Allow stacking with reward points @show_tooltip(__('lang_v1.coupon_stack_reward_points_help'))
                        </label>
                    </div>
                </div>
            </div>

            <div class="tw-flex tw-gap-2 tw-mt-4">
                <button type="submit" class="tw-dw-btn tw-dw-btn-primary">@lang('messages.save')</button>
                <a href="{{ action([\App\Http\Controllers\CouponController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-ghost">@lang('messages.cancel')</a>
            </div>

            {!! Form::close() !!}
        </div>
    </section>
@stop

@section('javascript')
    <script type="text/javascript">
        function toggleCouponTypeFields() {
            var type = $('#coupon_type').val();
            var isFreeShipping = type === '{{ \App\Coupon::TYPE_FREE_SHIPPING }}';
            var isPercent = type === '{{ \App\Coupon::TYPE_PERCENT_ORDER }}';
            $('#discount_amount_wrap').toggle(!isFreeShipping);
            $('#max_discount_wrap').toggle(isPercent);
            $('#discount_amount').prop('required', !isFreeShipping);
        }

        function toggleAppliesToFields() {
            var applies = $('#applies_to').val();
            $('#category_ids_wrap').toggle(applies === '{{ \App\Coupon::APPLIES_CATEGORIES }}');
            $('#variation_ids_wrap').toggle(applies === '{{ \App\Coupon::APPLIES_PRODUCTS }}');
        }

        $(document).ready(function() {
            toggleCouponTypeFields();
            toggleAppliesToFields();
            $('#coupon_type').on('change', toggleCouponTypeFields);
            $('#applies_to').on('change', toggleAppliesToFields);

            $('#generate_coupon_code').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true);
                $.ajax({
                    method: 'GET',
                    url: '{{ action([\App\Http\Controllers\CouponController::class, 'generateCode']) }}',
                    dataType: 'json',
                    success: function(result) {
                        if (result.success && result.code) {
                            $('#coupon_code').val(result.code);
                            var $name = $('input[name="name"]');
                            if ($name.val().trim() === '') {
                                $name.val(result.code);
                            }
                            toastr.success('Promo code generated.');
                        } else {
                            toastr.error('Could not generate promo code.');
                        }
                    },
                    error: function() {
                        toastr.error('Could not generate promo code.');
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            });
        });
    </script>
@endsection
