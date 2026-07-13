@extends('layouts.app')
@section('title', __('installmentcredit::lang.import_excel'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('installmentcredit::lang.import_excel')</h1>
</section>
<section class="content">
    <div class="box box-primary">
        <div class="box-body">
            <p>@lang('installmentcredit::lang.import_help')</p>
            <p>
                <a href="{{ url('/installment-credit/import/template-xlsx') }}" class="tw-dw-btn tw-dw-btn-success tw-text-white">
                    <i class="fa fa-file-excel"></i> @lang('installmentcredit::lang.download_xlsx_sample')
                </a>
                <a href="{{ url('/installment-credit/import/template') }}" class="tw-dw-btn tw-dw-btn-info tw-text-white">
                    <i class="fa fa-download"></i> @lang('installmentcredit::lang.download_csv_template')
                </a>
            </p>
            {!! Form::open(['url' => url('/installment-credit/import'), 'method' => 'post', 'files' => true]) !!}
                <div class="form-group">
                    {!! Form::label('file', __('lang_v1.file') . ':*') !!}
                    {!! Form::file('file', ['class' => 'form-control', 'required', 'accept' => '.xlsx,.xls,.csv,text/csv']) !!}
                    <p class="help-block">@lang('installmentcredit::lang.import_file_types')</p>
                </div>
                <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('messages.submit')</button>
            {!! Form::close() !!}
        </div>
    </div>
</section>
@endsection
