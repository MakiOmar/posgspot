@extends('layouts.app')
@section('title', __('installmentcredit::lang.settle'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('installmentcredit::lang.settle') — {{ $company->name }}</h1>
</section>
<section class="content">
    {!! Form::open(['url' => url('/installment-credit/receivables/settle'), 'method' => 'post', 'id' => 'settle_form']) !!}
    {!! Form::hidden('company_id', $company->id) !!}
    <div class="box box-primary">
        <div class="box-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('settlement_date', __('installmentcredit::lang.settlement_date') . ':*') !!}
                        {!! Form::text('settlement_date', @format_date('now'), ['class' => 'form-control', 'required', 'readonly', 'id' => 'settlement_date']) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('account_id', __('lang_v1.payment_account') . ':') !!}
                        {!! Form::select('account_id', $accounts, $company->default_deposit_account_id, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('messages.please_select')]) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('location_id', __('purchase.business_location') . ':') !!}
                        {!! Form::select('location_id', $locations, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('messages.please_select')]) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('external_ref', __('installmentcredit::lang.external_ref') . ':') !!}
                        {!! Form::text('external_ref', null, ['class' => 'form-control']) !!}
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>@lang('sale.invoice_no')</th>
                            <th>@lang('installmentcredit::lang.outstanding')</th>
                            <th>@lang('installmentcredit::lang.amount_booked')</th>
                            <th>@lang('installmentcredit::lang.amount_received')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($receivables as $i => $recv)
                        <tr>
                            <td>
                                {{ $recv->invoice_no }}
                                <input type="hidden" name="lines[{{ $i }}][receivable_id]" value="{{ $recv->id }}">
                            </td>
                            <td>@format_currency($recv->outstanding)</td>
                            <td>
                                <input type="text" class="form-control input_number booked_amt" name="lines[{{ $i }}][amount_booked]" value="{{ @num_format($recv->outstanding) }}" required>
                            </td>
                            <td>
                                <input type="text" class="form-control input_number received_amt" name="lines[{{ $i }}][amount_received]" value="{{ @num_format($recv->outstanding) }}" required>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="form-group">
                {!! Form::label('notes', __('lang_v1.notes') . ':') !!}
                {!! Form::textarea('notes', null, ['class' => 'form-control', 'rows' => 2]) !!}
            </div>
        </div>
        <div class="box-footer">
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('messages.save')</button>
            <a href="{{ url('/installment-credit/receivables') }}" class="tw-dw-btn tw-dw-btn-neutral tw-text-white">@lang('messages.cancel')</a>
        </div>
    </div>
    {!! Form::close() !!}
</section>
@endsection

@section('javascript')
<script>
$(document).ready(function() {
    $('#settlement_date').datepicker({ autoclose: true, format: datepicker_date_format });
    $('.select2').select2();
});
</script>
@endsection
