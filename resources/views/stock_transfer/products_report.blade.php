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
            <div id="stock_transfer_products_report_form">
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('stpr_search_product', __('lang_v1.search_product') . ':') !!}
                        <div class="input-group">
                            <span class="input-group-addon">
                                <i class="fa fa-search"></i>
                            </span>
                            <input type="hidden" value="" id="stpr_variation_id">
                            {!! Form::text('stpr_search_product', null, ['class' => 'form-control', 'id' => 'stpr_search_product', 'placeholder' => __('lang_v1.search_product_placeholder')]); !!}
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('stpr_location_from_id', __('lang_v1.location_from') . ':') !!}
                        <div class="input-group">
                            <span class="input-group-addon">
                                <i class="fa fa-map-marker"></i>
                            </span>
                            {!! Form::select('stpr_location_from_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%;', 'id' => 'stpr_location_from_id', 'placeholder' => __('lang_v1.all')]); !!}
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('stpr_location_to_id', __('lang_v1.location_to') . ':') !!}
                        <div class="input-group">
                            <span class="input-group-addon">
                                <i class="fa fa-map-marker"></i>
                            </span>
                            {!! Form::select('stpr_location_to_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%;', 'id' => 'stpr_location_to_id', 'placeholder' => __('lang_v1.all')]); !!}
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('stpr_date_filter', __('report.date_range') . ':') !!}
                        <div class="input-group">
                            <span class="input-group-addon">
                                <i class="fa fa-calendar"></i>
                            </span>
                            {!! Form::text('stpr_date_filter', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'stpr_date_filter', 'readonly']); !!}
                        </div>
                    </div>
                </div>
            </div>
            @endcomponent
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.stock_transfer_products_report')])
                <div class="table-responsive">
                    <table class="table table-bordered table-striped ajax_view" id="stock_transfer_products_report_table">
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
                                <td id="stpr_footer_total_qty"></td>
                                <td></td>
                                <td><span class="display_currency" id="stpr_footer_line_subtotal" data-currency_symbol="true"></span></td>
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
<script type="text/javascript">
    $(document).ready(function() {
        if (!$('#stock_transfer_products_report_table').length) {
            return;
        }

        var stpr_report_url = '{{ action([\App\Http\Controllers\StockTransferController::class, 'productsReport']) }}';

        var stpr_datatable = $('#stock_transfer_products_report_table').DataTable({
            processing: true,
            serverSide: true,
            fixedHeader: false,
            aaSorting: [[4, 'desc']],
            ajax: {
                url: stpr_report_url,
                data: function(d) {
                    var start = '';
                    var end = '';
                    if ($('#stpr_date_filter').val()) {
                        start = $('#stpr_date_filter').data('daterangepicker').startDate.format('YYYY-MM-DD');
                        end = $('#stpr_date_filter').data('daterangepicker').endDate.format('YYYY-MM-DD');
                    }
                    d.start_date = start;
                    d.end_date = end;
                    d.variation_id = $('#stpr_variation_id').val();
                    d.product_name = $('#stpr_search_product').val();
                    d.location_from_id = $('#stpr_location_from_id').val();
                    d.location_to_id = $('#stpr_location_to_id').val();
                },
            },
            columns: [
                { data: 'product_name', name: 'p.name' },
                { data: 'sub_sku', name: 'v.sub_sku' },
                { data: 'stock_transfer_id', name: 't.id' },
                { data: 'ref_no', name: 't.ref_no' },
                { data: 'transaction_date', name: 't.transaction_date' },
                { data: 'location_from', name: 'l1.name' },
                { data: 'location_to', name: 'l2.name' },
                { data: 'status', name: 't.status' },
                { data: 'quantity', name: 'transaction_sell_lines.quantity' },
                { data: 'unit_price_inc_tax', name: 'transaction_sell_lines.unit_price_inc_tax' },
                { data: 'line_subtotal', name: 'line_subtotal', searchable: false },
                { data: 'shipping_charges', name: 't.shipping_charges' },
                { data: 'final_total', name: 't.final_total' },
                { data: 'additional_notes', name: 't.additional_notes' },
                { data: 'action', name: 'action', searchable: false, orderable: false },
            ],
            fnDrawCallback: function(oSettings) {
                $('#stpr_footer_line_subtotal').text(
                    sum_table_col($('#stock_transfer_products_report_table'), 'row_subtotal')
                );
                $('#stpr_footer_total_qty').html(
                    __sum_stock($('#stock_transfer_products_report_table'), 'transfer_qty')
                );
                __currency_convert_recursively($('#stock_transfer_products_report_table'));
            },
        });

        if ($('#stpr_date_filter').length) {
            $('#stpr_date_filter').daterangepicker(dateRangeSettings, function(start, end) {
                $('#stpr_date_filter').val(
                    start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format)
                );
                stpr_datatable.ajax.reload();
            });
            $('#stpr_date_filter').on('cancel.daterangepicker', function(ev, picker) {
                $('#stpr_date_filter').val('');
                stpr_datatable.ajax.reload();
            });
        }

        $(document).on(
            'change',
            '#stpr_location_from_id, #stpr_location_to_id, #stpr_variation_id',
            function() {
                stpr_datatable.ajax.reload();
            }
        );

        if ($('#stpr_search_product').length) {
            $('#stpr_search_product').autocomplete({
                source: function(request, response) {
                    $.ajax({
                        url: '{{ url('/purchases/get_products') }}?check_enable_stock=false',
                        dataType: 'json',
                        data: { term: request.term },
                        success: function(data) {
                            response(
                                $.map(data, function(v) {
                                    if (v.variation_id) {
                                        return { label: v.text, value: v.variation_id };
                                    }
                                    return null;
                                })
                            );
                        },
                    });
                },
                minLength: 2,
                select: function(event, ui) {
                    $('#stpr_variation_id').val(ui.item.value).trigger('change');
                    event.preventDefault();
                    $(this).val(ui.item.label);
                },
                focus: function(event, ui) {
                    event.preventDefault();
                    $(this).val(ui.item.label);
                },
            });

            var stpr_search_timer;
            $('#stpr_search_product').on('keyup', function() {
                if ($(this).val().trim() === '') {
                    $('#stpr_variation_id').val('').trigger('change');
                    return;
                }
                if ($('#stpr_variation_id').val()) {
                    return;
                }
                clearTimeout(stpr_search_timer);
                stpr_search_timer = setTimeout(function() {
                    stpr_datatable.ajax.reload();
                }, 600);
            });
        }
    });
</script>
@endsection

@cannot('view_purchase_price')
    <style>
        .show_price_with_permission {
            display: none !important;
        }
    </style>
@endcannot
