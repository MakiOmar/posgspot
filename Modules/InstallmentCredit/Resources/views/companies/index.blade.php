@extends('layouts.app')
@section('title', __('installmentcredit::lang.companies'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('installmentcredit::lang.companies')</h1>
</section>
<section class="content">
    @component('components.widget', ['class' => 'box-primary', 'title' => __('installmentcredit::lang.companies')])
        @slot('tool')
            <div class="box-tools">
                <button type="button" class="tw-dw-btn tw-dw-btn-primary tw-text-white btn-modal"
                    data-href="{{ action([\Modules\InstallmentCredit\Http\Controllers\CompanyController::class, 'create']) }}"
                    data-container=".view_modal">
                    <i class="fa fa-plus"></i> @lang('installmentcredit::lang.add_company')
                </button>
                <button type="button" class="tw-dw-btn tw-dw-btn-info tw-text-white" id="seed_defaults_btn"
                    data-href="{{ action([\Modules\InstallmentCredit\Http\Controllers\CompanyController::class, 'seedDefaults']) }}">
                    <i class="fa fa-magic"></i> @lang('installmentcredit::lang.seed_defaults')
                </button>
                <button type="button" class="tw-dw-btn tw-dw-btn-warning tw-text-white" id="remap_payments_btn"
                    data-href="{{ action([\Modules\InstallmentCredit\Http\Controllers\CompanyController::class, 'remapPayments']) }}">
                    <i class="fa fa-exchange-alt"></i> @lang('installmentcredit::lang.remap_payments')
                </button>
            </div>
        @endslot
        <p class="help-block">@lang('installmentcredit::lang.map_payment_help')</p>
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="companies_table" width="100%">
                <thead>
                    <tr>
                        <th>@lang('messages.action')</th>
                        <th>@lang('lang_v1.name')</th>
                        <th>@lang('installmentcredit::lang.code')</th>
                        <th>@lang('installmentcredit::lang.payment_method_key')</th>
                        <th>@lang('installmentcredit::lang.settlement_days')</th>
                        <th>@lang('installmentcredit::lang.is_active')</th>
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
    var table = $('#companies_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ action([\Modules\InstallmentCredit\Http\Controllers\CompanyController::class, "index"]) }}',
        columns: [
            { data: 'action', orderable: false, searchable: false },
            { data: 'name', name: 'name' },
            { data: 'code', name: 'code' },
            { data: 'payment_method_key', name: 'payment_method_key' },
            { data: 'default_settlement_days', name: 'default_settlement_days' },
            { data: 'is_active', name: 'is_active' }
        ]
    });

    $(document).on('click', '.delete-company', function(e) {
        e.preventDefault();
        var url = $(this).data('href');
        swal({
            title: LANG.sure,
            icon: 'warning',
            buttons: true,
            dangerMode: true,
        }).then(function(willDelete) {
            if (willDelete) {
                $.ajax({
                    method: 'DELETE',
                    url: url,
                    dataType: 'json',
                    success: function(result) {
                        if (result.success) {
                            toastr.success(result.msg);
                            table.ajax.reload();
                        } else {
                            toastr.error(result.msg);
                        }
                    }
                });
            }
        });
    });

    function postCompanyAction(url) {
        $.ajax({
            method: 'POST',
            url: url,
            data: { _token: '{{ csrf_token() }}' },
            dataType: 'json',
            success: function(result) {
                if (result.success) {
                    toastr.success(result.msg);
                    table.ajax.reload();
                } else {
                    toastr.error(result.msg);
                }
            }
        });
    }

    $('#seed_defaults_btn').click(function() {
        postCompanyAction($(this).data('href'));
    });

    $('#remap_payments_btn').click(function() {
        postCompanyAction($(this).data('href'));
    });
});
</script>
@endsection
