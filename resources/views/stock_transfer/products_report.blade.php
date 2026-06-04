@extends('layouts.app')
@section('title', __('lang_v1.stock_transfer_products_report'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.stock_transfer_products_report')</h1>
</section>

<!-- Main content -->
<section class="content no-print">
    <div class="row">
        <div class="col-md-12">
            @component('components.filters', ['title' => __('report.filters')])
            {!! Form::open(['url' => action([\App\Http\Controllers\StockTransferController::class, 'productsReport']), 'method' => 'get', 'id' => 'stock_transfer_products_report_form']) !!}
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('search_product', __('lang_v1.search_product') . ':') !!}
                        <div class="input-group">
                            <span class="input-group-addon">
                                <i class="fa fa-search"></i>
                            </span>
                            <input type="hidden" value="" id="variation_id" name="variation_id">
                            {!! Form::text('search_product', null, ['class' => 'form-control', 'id' => 'search_product', 'placeholder' => __('lang_v1.search_product_placeholder')]); !!}
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('location_from_id', __('lang_v1.location_from') . ':') !!}
                        <div class="input-group">
                            <span class="input-group-addon">
                                <i class="fa fa-map-marker"></i>
                            </span>
                            {!! Form::select('location_from_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%;', 'placeholder' => __('lang_v1.all')]); !!}
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('location_to_id', __('lang_v1.location_to') . ':') !!}
                        <div class="input-group">
                            <span class="input-group-addon">
                                <i class="fa fa-map-marker"></i>
                            </span>
                            {!! Form::select('location_to_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%;', 'placeholder' => __('lang_v1.all')]); !!}
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('stock_transfer_products_date_filter', __('report.date_range') . ':') !!}
                        <div class="input-group">
                            <span class="input-group-addon" id="stock_transfer_products_date_filter_trigger" style="cursor: pointer;">
                                <i class="fa fa-calendar"></i>
                            </span>
                            {!! Form::text('date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'stock_transfer_products_date_filter', 'readonly']); !!}
                        </div>
                    </div>
                </div>
            {!! Form::close() !!}
            @endcomponent
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.stock_transfer_products_report')])
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="stock_transfer_products_report_table">
                        <thead>
                            <tr>
                                <th>@lang('sale.product')</th>
                                <th>@lang('product.sku')</th>
                                <th>@lang('lang_v1.stock_transfer_id')</th>
                                <th>@lang('purchase.ref_no')</th>
                                <th>@lang('messages.date')</th>
                                <th>@lang('lang_v1.location_from')</th>
                                <th>@lang('lang_v1.location_to')</th>
                                <th>@lang('sale.status')</th>
                                <th>@lang('sale.qty')</th>
                                <th class="@cannot('view_purchase_price') show_price_with_permission no-print @endcan">@lang('sale.unit_price')</th>
                                <th class="@cannot('view_purchase_price') show_price_with_permission no-print @endcan">@lang('sale.subtotal')</th>
                                <th class="@cannot('view_purchase_price') show_price_with_permission no-print @endcan">@lang('lang_v1.shipping_charges')</th>
                                <th class="@cannot('view_purchase_price') show_price_with_permission no-print @endcan">@lang('stock_adjustment.total_amount')</th>
                                <th>@lang('purchase.additional_notes')</th>
                                <th>@lang('messages.action')</th>
                            </tr>
                        </thead>
                        <tfoot>
                            <tr class="bg-gray font-17 footer-total text-center">
                                <td colspan="8"><strong>@lang('sale.total'):</strong></td>
                                <td id="footer_total_qty"></td>
                                <td></td>
                                <td><span class="display_currency" id="footer_line_subtotal" data-currency_symbol="true"></span></td>
                                <td colspan="4"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endcomponent
        </div>
    </div>
</section>

@stop

@section('javascript')
    <script src="{{ asset('js/stock_transfer.js?v=' . $asset_v) }}"></script>
@endsection

@cannot('view_purchase_price')
    <style>
        .show_price_with_permission {
            display: none !important;
        }
    </style>
@endcannot
