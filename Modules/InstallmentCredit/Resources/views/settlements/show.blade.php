@extends('layouts.app')
@section('title', __('installmentcredit::lang.settlement') . ' #' . $settlement->id)

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('installmentcredit::lang.settlement') #{{ $settlement->id }}</h1>
</section>
<section class="content">
    <div class="box box-primary">
        <div class="box-body">
            <div class="row">
                <div class="col-md-4"><strong>@lang('installmentcredit::lang.company'):</strong> {{ $settlement->company->name ?? '' }}</div>
                <div class="col-md-4"><strong>@lang('installmentcredit::lang.settlement_date'):</strong> {{ @format_date($settlement->settlement_date) }}</div>
                <div class="col-md-4"><strong>@lang('installmentcredit::lang.external_ref'):</strong> {{ $settlement->external_ref }}</div>
            </div>
            <div class="row" style="margin-top:10px">
                <div class="col-md-4"><strong>@lang('installmentcredit::lang.amount_booked'):</strong> @format_currency($settlement->amount_booked)</div>
                <div class="col-md-4"><strong>@lang('installmentcredit::lang.amount_received'):</strong> @format_currency($settlement->amount_received)</div>
                <div class="col-md-4"><strong>@lang('installmentcredit::lang.fee_amount'):</strong> @format_currency($settlement->fee_amount)</div>
            </div>
            <div class="row" style="margin-top:10px">
                <div class="col-md-4"><strong>@lang('lang_v1.payment_account'):</strong> {{ $settlement->account->name ?? '-' }}</div>
                <div class="col-md-4"><strong>@lang('lang_v1.added_by'):</strong> {{ $settlement->creator->user_full_name ?? '' }}</div>
                <div class="col-md-4"><strong>@lang('lang_v1.notes'):</strong> {{ $settlement->notes }}</div>
            </div>

            <hr>
            <h4>@lang('lang_v1.lines')</h4>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>@lang('sale.invoice_no')</th>
                        <th>@lang('installmentcredit::lang.amount_booked')</th>
                        <th>@lang('installmentcredit::lang.amount_received')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($settlement->lines as $line)
                    <tr>
                        <td>{{ $line->receivable->invoice_no ?? '' }}</td>
                        <td>@format_currency($line->amount_booked)</td>
                        <td>@format_currency($line->amount_received)</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="box-footer">
            <a href="{{ action([\Modules\InstallmentCredit\Http\Controllers\SettlementController::class, 'edit'], [$settlement->id]) }}" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('messages.edit')</a>
            <button type="button" data-href="{{ action([\Modules\InstallmentCredit\Http\Controllers\SettlementController::class, 'destroy'], [$settlement->id]) }}" class="tw-dw-btn tw-dw-btn-error tw-text-white delete-settlement">@lang('messages.delete')</button>
            <a href="{{ url('/installment-credit/settlements') }}" class="tw-dw-btn tw-dw-btn-neutral tw-text-white">@lang('messages.back')</a>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
$(document).on('click', '.delete-settlement', function(e) {
    e.preventDefault();
    var url = $(this).data('href');
    swal({
        title: LANG.sure,
        text: {!! json_encode(__('installmentcredit::lang.delete_settlement_confirm')) !!},
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
                        window.location.href = '{{ url('/installment-credit/settlements') }}';
                    } else {
                        toastr.error(result.msg);
                    }
                }
            });
        }
    });
});
</script>
@endsection
