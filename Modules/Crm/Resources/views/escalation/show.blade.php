@extends('layouts.app')
@section('title', __('crm::lang.escalation_details'))

@section('content')
@include('crm::layouts.nav')

<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        @lang('crm::lang.escalation_details') — {{ $escalation->reference_no }}
        <div class="pull-right">
            @if($can_update)
                <a href="{{ action([\Modules\Crm\Http\Controllers\EscalationController::class, 'edit'], [$escalation->id]) }}" class="tw-dw-btn tw-dw-btn-primary tw-text-white">
                    <i class="fa fa-edit"></i> @lang('messages.edit')
                </a>
            @endif
            <a href="{{ action([\Modules\Crm\Http\Controllers\EscalationController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-neutral tw-text-white">
                <i class="fa fa-list"></i> @lang('crm::lang.escalations')
            </a>
        </div>
    </h1>
</section>

<section class="content no-print">
    <div class="row">
        <div class="col-md-8">
            @component('components.widget', ['class' => 'box-solid', 'title' => __('crm::lang.escalation_details')])
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>@lang('crm::lang.escalation_reference_no'):</strong> {{ $escalation->reference_no }}</p>
                        <p><strong>@lang('sale.status'):</strong>
                            <span class="label label-info">{{ $statuses[$escalation->status] ?? $escalation->status }}</span>
                        </p>
                        <p><strong>@lang('crm::lang.escalation_employee'):</strong>
                            {{ trim(($escalation->employee->surname ?? '') . ' ' . ($escalation->employee->first_name ?? '') . ' ' . ($escalation->employee->last_name ?? '')) }}
                        </p>
                        <p><strong>@lang('contact.customer'):</strong>
                            @if(!empty($escalation->contact->supplier_business_name))
                                {{ $escalation->contact->supplier_business_name }}<br>
                            @endif
                            {{ $escalation->contact->name ?? '' }}
                        </p>
                        <p><strong>@lang('crm::lang.escalation_phone'):</strong> {{ $escalation->phone }}</p>
                        <p><strong>@lang('crm::lang.escalation_escalated_at'):</strong> {{ @format_datetime($escalation->escalated_at) }}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>@lang('crm::lang.escalation_taken_by'):</strong> {{ $escalation->source->name ?? '' }}</p>
                        <p><strong>@lang('business.business_location'):</strong> {{ $escalation->location->name ?? '' }}</p>
                        <p><strong>@lang('crm::lang.escalation_time_to_call'):</strong>
                            @if(!empty($escalation->callback_at))
                                {{ @format_datetime($escalation->callback_at) }}
                            @else
                                —
                            @endif
                        </p>
                        <p><strong>@lang('crm::lang.escalation_invoice'):</strong> {{ $escalation->transaction->invoice_no ?? '—' }}</p>
                        <p><strong>@lang('crm::lang.escalation_observer'):</strong>
                            @if(!empty($escalation->observer))
                                {{ trim($escalation->observer->surname . ' ' . $escalation->observer->first_name . ' ' . $escalation->observer->last_name) }}
                            @else
                                —
                            @endif
                        </p>
                        <p><strong>@lang('crm::lang.escalation_auditor'):</strong>
                            @if(!empty($escalation->auditor))
                                {{ trim($escalation->auditor->surname . ' ' . $escalation->auditor->first_name . ' ' . $escalation->auditor->last_name) }}
                            @else
                                —
                            @endif
                        </p>
                    </div>
                </div>
                <hr>
                <p><strong>@lang('crm::lang.description'):</strong></p>
                <p>{{ $escalation->description }}</p>
                @if(!empty($escalation->comment))
                    <p><strong>@lang('crm::lang.escalation_comment'):</strong></p>
                    <p>{{ $escalation->comment }}</p>
                @endif
                @if(!empty($escalation->observer_comment))
                    <p><strong>@lang('crm::lang.escalation_observer_comment'):</strong></p>
                    <p>{{ $escalation->observer_comment }}</p>
                @endif
            @endcomponent
        </div>

        <div class="col-md-4">
            @if(!$escalation->isClosed() && ($can_update || auth()->user()->can('crm.escalation.close')))
            @component('components.widget', ['class' => 'box-solid', 'title' => __('crm::lang.escalation_change_status')])
                {!! Form::open(['url' => action([\Modules\Crm\Http\Controllers\EscalationController::class, 'updateStatus'], [$escalation->id]), 'method' => 'post', 'id' => 'escalation_status_form']) !!}
                <div class="form-group">
                    {!! Form::label('status', __('sale.status') . ':') !!}
                    {!! Form::select('status', $statuses, $escalation->status, ['class' => 'form-control select2', 'style' => 'width:100%']) !!}
                </div>
                <div class="form-group">
                    {!! Form::label('note', __('crm::lang.escalation_status_note') . ':') !!}
                    {!! Form::textarea('note', null, ['class' => 'form-control', 'rows' => 2]) !!}
                </div>
                <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white tw-dw-btn-sm">@lang('messages.update')</button>
                {!! Form::close() !!}
            @endcomponent
            @endif

            @component('components.widget', ['class' => 'box-solid', 'title' => __('crm::lang.escalation_status_history')])
                <ul class="list-group">
                    @forelse($escalation->statusLogs as $log)
                        <li class="list-group-item">
                            <strong>{{ $statuses[$log->to_status] ?? $log->to_status }}</strong>
                            @if(!empty($log->from_status))
                                <small class="text-muted">(@lang('lang_v1.from') {{ $statuses[$log->from_status] ?? $log->from_status }})</small>
                            @endif
                            <br>
                            <small>{{ @format_datetime($log->created_at) }}</small>
                            @if(!empty($log->user))
                                — {{ trim($log->user->surname . ' ' . $log->user->first_name) }}
                            @endif
                            @if(!empty($log->note))
                                <br><em>{{ $log->note }}</em>
                            @endif
                        </li>
                    @empty
                        <li class="list-group-item text-muted">@lang('lang_v1.no_data')</li>
                    @endforelse
                </ul>
            @endcomponent
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script type="text/javascript">
    $(document).ready(function() {
        $('.select2').select2();
    });
</script>
@endsection
