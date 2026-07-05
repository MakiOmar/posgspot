@extends('layouts.app')
@section('title', 'Promo codes')

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">Promo codes</h1>
        <p class="text-muted">Customer-entered codes for the storefront checkout (separate from automatic POS discounts).</p>
    </section>

    <section class="content">
        @can('storefront.settings')
            <div class="alert alert-info">
                <strong>Storefront checkout:</strong> control whether customers see the promo field and can stack multiple codes in
                <a href="{{ action([\App\Http\Controllers\StorefrontSettingController::class, 'edit']) }}#promo-checkout-settings">Storefront Settings → Promo codes (storefront checkout)</a>.
            </div>
        @endcan
        <div class="tw-transition-all lg:tw-col-span-1 tw-duration-200 tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 hover:tw-shadow-md tw-ring-gray-200">
            <div class="tw-p-4 sm:tw-p-5">
                <div class="tw-flex tw-gap-2.5 tw-justify-end">
                    @can('coupon.create')
                        <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right"
                            href="{{ action([\App\Http\Controllers\CouponController::class, 'create']) }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M12 5l0 14" />
                                <path d="M5 12l14 0" />
                            </svg> @lang('messages.add')
                        </a>
                    @endcan
                </div>
                <div class="tw-flow-root tw-mt-5 tw-border-b tw-border-gray-200">
                    <div class="tw-mx-4 tw--my-2 tw-overflow-x-auto sm:tw--mx-5">
                        <div class="tw-inline-block tw-min-w-full tw-py-2 tw-align-middle sm:tw-px-5">
                            @can('coupon.access')
                                <table class="table table-bordered table-striped" id="coupons_table">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>@lang('unit.name')</th>
                                            <th>Type</th>
                                            <th>@lang('sale.discount_amount')</th>
                                            <th>Usage</th>
                                            <th>@lang('lang_v1.starts_at')</th>
                                            <th>@lang('lang_v1.ends_at')</th>
                                            <th>Channel</th>
                                            <th>@lang('messages.action')</th>
                                        </tr>
                                    </thead>
                                </table>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@stop

@section('javascript')
    <script type="text/javascript">
        $(document).on('click', '.delete_coupon_button', function(e) {
            e.preventDefault();
            var href = $(this).data('href');
            swal({
                title: LANG.sure,
                icon: "warning",
                buttons: true,
                dangerMode: true,
            }).then((willDelete) => {
                if (willDelete) {
                    $.ajax({
                        method: "DELETE",
                        url: href,
                        dataType: "json",
                        success: function(result) {
                            if (result.success == true) {
                                toastr.success(result.msg);
                                coupons_table.ajax.reload();
                            } else {
                                toastr.error(result.msg);
                            }
                        }
                    });
                }
            });
        });

        $(document).on('click', '.activate-coupon', function(e) {
            e.preventDefault();
            var href = $(this).data('href');
            $.ajax({
                method: "get",
                url: href,
                dataType: "json",
                success: function(result) {
                    if (result.success == true) {
                        toastr.success(result.msg);
                        coupons_table.ajax.reload();
                    } else {
                        toastr.error(result.msg);
                    }
                }
            });
        });
    </script>
@endsection
