@extends('layouts.app')
@section('title', __('crm::lang.edit_escalation'))

@section('content')
@include('crm::layouts.nav')

<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        @lang('crm::lang.edit_escalation') — {{ $escalation->reference_no }}
    </h1>
</section>

<section class="content">
    {!! Form::open(['url' => action([\Modules\Crm\Http\Controllers\EscalationController::class, 'update'], [$escalation->id]), 'method' => 'PUT', 'id' => 'edit_escalation_form']) !!}
    <div class="box box-solid">
        <div class="box-body">
            @include('crm::escalation.partials.form', ['escalation' => $escalation])
        </div>
        <div class="box-footer">
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('messages.update')</button>
            <a href="{{ action([\Modules\Crm\Http\Controllers\EscalationController::class, 'show'], [$escalation->id]) }}" class="tw-dw-btn tw-dw-btn-neutral tw-text-white">@lang('messages.cancel')</a>
        </div>
    </div>
    {!! Form::close() !!}
</section>
@endsection

@section('javascript')
@include('crm::escalation.partials.form_js')
@endsection
