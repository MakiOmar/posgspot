@extends('layouts.app')
@section('title', $title)

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ $title }}</h1>
    </section>

    <section class="content">
        <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 sm:tw-p-6">
            {!! Form::open(['url' => $action, 'method' => $method, 'id' => 'coupon_form']) !!}

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('code', 'Promo code:*') !!}
                        {!! Form::text('code', old('code', $coupon->code ?? ''), ['class' => 'form-control', 'required', 'style' => 'text-transform: uppercase']) !!}
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="form-group">
                        {!! Form::label('name', __('unit.name') . ':*') !!}
                        {!! Form::text('name', old('name', $coupon->name ?? ''), ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-group">
                        {!! Form::label('description', __('lang_v1.description')) !!}
                        {!! Form::textarea('description', old('description', $coupon->description ?? ''), ['class' => 'form-control', 'rows' => 2]) !!}
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('type', 'Type:*') !!}
                        {!! Form::select('type', [
                            \App\Coupon::TYPE_PERCENT_ORDER => 'Percentage off order',
                            \App\Coupon::TYPE_FIXED_ORDER => 'Fixed amount off order',
                            \App\Coupon::TYPE_FREE_SHIPPING => 'Free shipping',
                        ], old('type', $coupon->type ?? \App\Coupon::TYPE_PERCENT_ORDER), ['class' => 'form-control select2', 'required', 'id' => 'coupon_type']) !!}
                    </div>
                </div>
                <div class="col-md-4" id="discount_amount_wrap">
                    <div class="form-group">
                        {!! Form::label('discount_amount', __('sale.discount_amount') . ':*') !!}
                        {!! Form::text('discount_amount', old('discount_amount', $coupon->discount_amount ?? ''), ['class' => 'form-control input_number', 'id' => 'discount_amount']) !!}
                    </div>
                </div>
                <div class="col-md-4" id="max_discount_wrap">
                    <div class="form-group">
                        {!! Form::label('max_discount_amount', 'Max discount cap (% only)') !!}
                        {!! Form::text('max_discount_amount', old('max_discount_amount', $coupon->max_discount_amount ?? ''), ['class' => 'form-control input_number']) !!}
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('min_order_subtotal', 'Min eligible subtotal') !!}
                        {!! Form::text('min_order_subtotal', old('min_order_subtotal', $coupon->min_order_subtotal ?? 0), ['class' => 'form-control input_number']) !!}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('channel', 'Channel:*') !!}
                        {!! Form::select('channel', [
                            \App\Coupon::CHANNEL_STOREFRONT => 'Storefront only',
                            \App\Coupon::CHANNEL_POS => 'POS only',
                            \App\Coupon::CHANNEL_BOTH => 'Storefront and POS',
                        ], old('channel', $coupon->channel ?? \App\Coupon::CHANNEL_STOREFRONT), ['class' => 'form-control select2', 'required']) !!}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('applies_to', 'Applies to:*') !!}
                        {!! Form::select('applies_to', [
                            \App\Coupon::APPLIES_ALL => 'Entire cart',
                            \App\Coupon::APPLIES_CATEGORIES => 'Selected categories',
                            \App\Coupon::APPLIES_PRODUCTS => 'Selected products',
                        ], old('applies_to', $coupon->applies_to ?? \App\Coupon::APPLIES_ALL), ['class' => 'form-control select2', 'required', 'id' => 'applies_to']) !!}
                    </div>
                </div>

                <div class="col-md-12" id="category_ids_wrap">
                    <div class="form-group">
                        {!! Form::label('category_ids', __('product.category')) !!}
                        {!! Form::select('category_ids[]', $categories, old('category_ids', $coupon ? $coupon->categories->pluck('id')->all() : []), ['class' => 'form-control select2', 'multiple', 'id' => 'category_ids']) !!}
                    </div>
                </div>
                <div class="col-md-12" id="variation_ids_wrap">
                    <div class="form-group">
                        {!! Form::label('variation_ids', __('report.products')) !!}
                        {!! Form::select('variation_ids[]', $variations, old('variation_ids', $coupon ? $coupon->variations->pluck('id')->all() : []), ['class' => 'form-control select2', 'multiple', 'id' => 'variation_ids']) !!}
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('starts_at', __('lang_v1.starts_at')) !!}
                        {!! Form::text('starts_at', old('starts_at', $starts_at), ['class' => 'form-control discount_date', 'readonly']) !!}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('ends_at', __('lang_v1.ends_at')) !!}
                        {!! Form::text('ends_at', old('ends_at', $ends_at), ['class' => 'form-control discount_date', 'readonly']) !!}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('max_uses_total', 'Max total redemptions') !!}
                        {!! Form::number('max_uses_total', old('max_uses_total', $coupon->max_uses_total ?? ''), ['class' => 'form-control', 'min' => 0, 'placeholder' => 'Unlimited']) !!}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('max_uses_per_customer', 'Max per customer') !!}
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
                            @lang('lang_v1.active')
                        </label>
                    </div>
                    <div class="checkbox">
                        <label>
                            {!! Form::checkbox('first_order_only', 1, old('first_order_only', $coupon->first_order_only ?? false), ['class' => 'input-icheck']) !!}
                            First storefront order only
                        </label>
                    </div>
                    <div class="checkbox">
                        <label>
                            {!! Form::checkbox('exclude_sale_items', 1, old('exclude_sale_items', $coupon->exclude_sale_items ?? false), ['class' => 'input-icheck']) !!}
                            Exclude sale-priced items from eligible subtotal
                        </label>
                    </div>
                    <div class="checkbox">
                        <label>
                            {!! Form::checkbox('stack_with_reward_points', 1, old('stack_with_reward_points', $coupon->stack_with_reward_points ?? true), ['class' => 'input-icheck']) !!}
                            Allow stacking with reward points
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
        });
    </script>
@endsection
