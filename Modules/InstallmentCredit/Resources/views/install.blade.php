@extends('layouts.app')
@section('title', __('installmentcredit::lang.installment_credit'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('installmentcredit::lang.install_module')</h1>
</section>
<section class="content">
    <div class="box box-primary">
        <div class="box-body">
            <p>@lang('installmentcredit::lang.install_help')</p>
            {!! Form::open(['url' => $action_url, 'method' => 'post']) !!}
                <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('installmentcredit::lang.install_module')</button>
            {!! Form::close() !!}
        </div>
    </div>
</section>
@endsection
