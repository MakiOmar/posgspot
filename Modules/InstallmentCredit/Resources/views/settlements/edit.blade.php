@extends('layouts.app')
@section('title', __('messages.edit') . ' ' . __('installmentcredit::lang.settlement') . ' #' . $settlement->id)

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        @lang('messages.edit') @lang('installmentcredit::lang.settlement') #{{ $settlement->id }} — {{ $company->name ?? '' }}
    </h1>
</section>
<section class="content">
    {!! Form::open(['url' => action([\Modules\InstallmentCredit\Http\Controllers\SettlementController::class, 'update'], [$settlement->id]), 'method' => 'put', 'id' => 'settle_edit_form']) !!}
    <div class="box box-primary">
        <div class="box-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('settlement_date', __('installmentcredit::lang.settlement_date') . ':*') !!}
                        {!! Form::text('settlement_date', @format_date($settlement->settlement_date), ['class' => 'form-control', 'required', 'readonly', 'id' => 'settlement_date']) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('account_id', __('lang_v1.payment_account') . ':') !!}
                        {!! Form::select('account_id', $accounts, $settlement->account_id, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('messages.please_select')]) !!}
                        <p class="help-block">@lang('installmentcredit::lang.payment_account_settle_help')</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('location_id', __('purchase.business_location') . ':') !!}
                        {!! Form::select('location_id', $locations, $settlement->location_id, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('messages.please_select')]) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('external_ref', __('installmentcredit::lang.external_ref') . ':') !!}
                        {!! Form::text('external_ref', $settlement->external_ref, ['class' => 'form-control']) !!}
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
                            <th>
                                @lang('installmentcredit::lang.amount_received')
                                <br><small class="text-muted">@lang('installmentcredit::lang.amount_received_help')</small>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($line_rows as $i => $row)
                        @php
                            $line = $row['line'];
                            $recv = $row['receivable'];
                        @endphp
                        <tr>
                            <td>
                                {{ $recv->invoice_no ?? '' }}
                                <input type="hidden" name="lines[{{ $i }}][receivable_id]" value="{{ $line->receivable_id }}">
                            </td>
                            <td>@format_currency($row['outstanding'])</td>
                            <td>
                                <input type="text" class="form-control input_number booked_amt" name="lines[{{ $i }}][amount_booked]" value="{{ @num_format($line->amount_booked) }}" required>
                            </td>
                            <td>
                                <input type="text" class="form-control input_number received_amt" name="lines[{{ $i }}][amount_received]" value="{{ @num_format($line->amount_received) }}" required>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="form-group">
                {!! Form::label('notes', __('lang_v1.notes') . ':') !!}
                {!! Form::textarea('notes', $settlement->notes, ['class' => 'form-control', 'rows' => 2]) !!}
            </div>
        </div>
        <div class="box-footer">
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('messages.update')</button>
            <a href="{{ url('/installment-credit/settlements/'.$settlement->id) }}" class="tw-dw-btn tw-dw-btn-neutral tw-text-white">@lang('messages.cancel')</a>
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
