@extends('layouts.app')
@section('title', __('installmentcredit::lang.import_excel'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('installmentcredit::lang.import_excel')</h1>
</section>
<section class="content">
    <div class="box box-primary">
        <div class="box-body">
            <p>Import <strong>open</strong> balances only (Excel rows where Total ≠ 0). Columns: invoice_date, due_date, invoice_no, branch, company_code, due_amount, notes.</p>
            <p>
                <a href="{{ url('/installment-credit/import/template') }}" class="tw-dw-btn tw-dw-btn-info tw-text-white">
                    <i class="fa fa-download"></i> @lang('installmentcredit::lang.download_template')
                </a>
            </p>
            {!! Form::open(['url' => url('/installment-credit/import'), 'method' => 'post', 'files' => true]) !!}
                <div class="form-group">
                    {!! Form::label('file', __('lang_v1.file') . ':*') !!}
                    {!! Form::file('file', ['class' => 'form-control', 'required', 'accept' => '.csv,text/csv']) !!}
                </div>
                <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('messages.submit')</button>
            {!! Form::close() !!}
        </div>
    </div>
</section>
@endsection
