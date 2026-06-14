@extends('layouts.app')
@section('title', __('crm::lang.add_escalation'))

@section('content')
@include('crm::layouts.nav')

<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('crm::lang.add_escalation')</h1>
</section>

<section class="content">
    {!! Form::open(['url' => action([\Modules\Crm\Http\Controllers\EscalationController::class, 'store']), 'method' => 'post', 'id' => 'add_escalation_form']) !!}
    <div class="box box-solid">
        <div class="box-body">
            @include('crm::escalation.partials.form')
        </div>
        <div class="box-footer">
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('messages.save')</button>
            <a href="{{ action([\Modules\Crm\Http\Controllers\EscalationController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-neutral tw-text-white">@lang('messages.cancel')</a>
        </div>
    </div>
    {!! Form::close() !!}
</section>
@endsection

@section('javascript')
@include('crm::escalation.partials.form_js')
@endsection
