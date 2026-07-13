@extends('layouts.app')
@section('title', __('installmentcredit::lang.pending_receivables'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('installmentcredit::lang.pending_receivables')</h1>
</section>
<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('filter_company_id', __('installmentcredit::lang.company') . ':') !!}
                {!! Form::select('filter_company_id', $companies, null, ['class' => 'form-control select2', 'id' => 'filter_company_id', 'placeholder' => __('messages.all'), 'style' => 'width:100%']) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('filter_location_id', __('purchase.business_location') . ':') !!}
                {!! Form::select('filter_location_id', $locations, null, ['class' => 'form-control select2', 'id' => 'filter_location_id', 'placeholder' => __('messages.all'), 'style' => 'width:100%']) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('filter_aging', __('lang_v1.aging') . ':') !!}
                {!! Form::select('filter_aging', [
                    '' => __('messages.all'),
                    '30' => __('installmentcredit::lang.aging_30'),
                    '60' => __('installmentcredit::lang.aging_60'),
                    '90' => __('installmentcredit::lang.aging_90'),
                    '120' => __('installmentcredit::lang.aging_120'),
                    'gt120' => __('installmentcredit::lang.aging_gt120'),
                ], null, ['class' => 'form-control', 'id' => 'filter_aging']) !!}
            </div>
        </div>
    @endcomponent

    @component('components.widget', ['class' => 'box-primary'])
        @slot('tool')
            <div class="box-tools">
                <button type="button" class="tw-dw-btn tw-dw-btn-success tw-text-white" id="btn_settle_selected">
                    <i class="fa fa-handshake"></i> @lang('installmentcredit::lang.settle')
                </button>
            </div>
        @endslot
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="receivables_table" width="100%">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select_all_rows"></th>
                        <th>@lang('sale.invoice_no')</th>
                        <th>@lang('installmentcredit::lang.invoice_date')</th>
                        <th>@lang('installmentcredit::lang.due_date')</th>
                        <th>@lang('installmentcredit::lang.days_due')</th>
                        <th>@lang('purchase.business_location')</th>
                        <th>@lang('installmentcredit::lang.company')</th>
                        <th>@lang('installmentcredit::lang.due_amount')</th>
                        <th>@lang('installmentcredit::lang.outstanding')</th>
                    </tr>
                </thead>
            </table>
        </div>
    @endcomponent
</section>
@endsection

@section('javascript')
<script>
$(document).ready(function() {
    var table = $('#receivables_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ url("/installment-credit/receivables") }}',
            data: function(d) {
                d.company_id = $('#filter_company_id').val();
                d.location_id = $('#filter_location_id').val();
                d.aging = $('#filter_aging').val();
            }
        },
        columns: [
            { data: 'mass_select', orderable: false, searchable: false },
            { data: 'invoice_no', name: 'invoice_no' },
            { data: 'invoice_date', name: 'invoice_date' },
            { data: 'due_date', name: 'due_date' },
            { data: 'days_due', name: 'days_due', searchable: false },
            { data: 'location_name', name: 'location_name' },
            { data: 'company_name', name: 'company_name' },
            { data: 'due_amount', name: 'due_amount' },
            { data: 'outstanding', name: 'outstanding', searchable: false }
        ]
    });

    $('#filter_company_id, #filter_location_id, #filter_aging').change(function() {
        table.ajax.reload();
    });

    $('#select_all_rows').on('change', function() {
        $('.row-select').prop('checked', $(this).is(':checked'));
    });

    $('#btn_settle_selected').click(function() {
        var ids = [];
        var company = null;
        var ok = true;
        $('.row-select:checked').each(function() {
            var c = $(this).data('company');
            if (company === null) company = c;
            if (company != c) ok = false;
            ids.push($(this).val());
        });
        if (!ids.length) {
            toastr.error('@lang("messages.nothing_selected")');
            return;
        }
        if (!ok) {
            toastr.error('@lang("installmentcredit::lang.settle_one_company_only")');
            return;
        }
        window.location = '{{ url("/installment-credit/receivables/create-settlement") }}?ids=' + ids.join(',');
    });
});
</script>
@endsection
