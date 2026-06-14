{{-- Escalation form fields shared by create and edit --}}
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            {!! Form::label('employee_id', __('crm::lang.escalation_employee') . ':*') !!}
            <select name="employee_id" id="escalation_employee_id" class="form-control" required style="width: 100%;">
                @if(!empty($escalation) && !empty($escalation->employee))
                    <option value="{{ $escalation->employee_id }}" selected>{{ trim($escalation->employee->surname . ' ' . $escalation->employee->first_name . ' ' . $escalation->employee->last_name) }}</option>
                @elseif(!empty($default_employee_id))
                    <option value="{{ $default_employee_id }}" selected>{{ trim(auth()->user()->surname . ' ' . auth()->user()->first_name . ' ' . auth()->user()->last_name) }}</option>
                @endif
            </select>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            {!! Form::label('contact_id', __('contact.customer') . ':*') !!}
            <select name="contact_id" id="escalation_contact_id" class="form-control" required style="width: 100%;">
                @if(!empty($escalation) && !empty($escalation->contact))
                    <option value="{{ $escalation->contact_id }}" selected data-mobile="{{ $escalation->phone }}">{{ $escalation->contact->name }}</option>
                @endif
            </select>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            {!! Form::label('phone', __('crm::lang.escalation_phone') . ':') !!}
            {!! Form::text('phone', !empty($escalation) ? $escalation->phone : null, ['class' => 'form-control', 'id' => 'escalation_phone', 'placeholder' => __('crm::lang.escalation_phone')]) !!}
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            {!! Form::label('escalated_at', __('crm::lang.escalation_escalated_at') . ':*') !!}
            {!! Form::text('escalated_at', !empty($escalated_at_formatted) ? $escalated_at_formatted : (!empty($default_escalated_at) ? $default_escalated_at : null), ['class' => 'form-control datetimepicker', 'required', 'readonly', 'id' => 'escalation_escalated_at']) !!}
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            {!! Form::label('source_id', __('crm::lang.escalation_taken_by') . ':*') !!}
            {!! Form::select('source_id', $sources, !empty($escalation) ? $escalation->source_id : null, ['class' => 'form-control', 'required', 'style' => 'width: 100%;', 'id' => 'source_id', 'placeholder' => __('messages.please_select')]) !!}
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            {!! Form::label('location_id', __('business.business_location') . ':*') !!}
            {!! Form::select('location_id', $locations, !empty($escalation) ? $escalation->location_id : null, ['class' => 'form-control', 'required', 'style' => 'width: 100%;', 'id' => 'location_id', 'placeholder' => __('messages.please_select')]) !!}
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label('description', __('crm::lang.description') . ':*') !!}
            {!! Form::textarea('description', !empty($escalation) ? $escalation->description : null, ['class' => 'form-control', 'required', 'rows' => 3]) !!}
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            {!! Form::label('callback_at', __('crm::lang.escalation_time_to_call') . ':') !!}
            {!! Form::text('callback_at', !empty($callback_at_formatted) ? $callback_at_formatted : null, ['class' => 'form-control datetimepicker', 'readonly', 'id' => 'escalation_callback_at']) !!}
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            {!! Form::label('transaction_id', __('crm::lang.escalation_invoice') . ':') !!}
            {{-- Empty option required for Select2 AJAX search to render properly --}}
            <select name="transaction_id" id="escalation_transaction_id" class="form-control escalation-invoice-select" style="width: 100%;">
                <option value="">{{ __('messages.please_select') }}</option>
                @if(!empty($escalation) && !empty($escalation->transaction))
                    <option value="{{ $escalation->transaction_id }}" selected>{{ $escalation->transaction->invoice_no }}</option>
                @endif
            </select>
            <p class="help-block">@lang('crm::lang.escalation_invoice_search_help')</p>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label('comment', __('crm::lang.escalation_comment') . ':') !!}
            {!! Form::textarea('comment', !empty($escalation) ? $escalation->comment : null, ['class' => 'form-control', 'rows' => 3]) !!}
        </div>
    </div>
</div>

<div class="row">
    @if(auth()->user()->can('crm.escalation.assign_observer') || !empty($escalation))
    <div class="col-md-4">
        <div class="form-group">
            {!! Form::label('observer_id', __('crm::lang.escalation_observer') . ':') !!}
            <select name="observer_id" id="escalation_observer_id" class="form-control" style="width: 100%;" @if(!auth()->user()->can('crm.escalation.assign_observer') && !empty($escalation)) disabled @endif>
                @if(!empty($escalation) && !empty($escalation->observer))
                    <option value="{{ $escalation->observer_id }}" selected>{{ trim($escalation->observer->surname . ' ' . $escalation->observer->first_name . ' ' . $escalation->observer->last_name) }}</option>
                @endif
            </select>
            @if(!auth()->user()->can('crm.escalation.assign_observer') && !empty($escalation) && !empty($escalation->observer_id))
                <input type="hidden" name="observer_id" value="{{ $escalation->observer_id }}">
            @endif
        </div>
    </div>
    @endif
    <div class="col-md-8">
        <div class="form-group">
            {!! Form::label('observer_comment', __('crm::lang.escalation_observer_comment') . ':') !!}
            {!! Form::textarea('observer_comment', !empty($escalation) ? $escalation->observer_comment : null, ['class' => 'form-control', 'rows' => 3, 'id' => 'escalation_observer_comment']) !!}
        </div>
    </div>
</div>

<div class="row">
    @if(auth()->user()->can('crm.escalation.assign_auditor') || !empty($escalation))
    <div class="col-md-4">
        <div class="form-group">
            {!! Form::label('auditor_id', __('crm::lang.escalation_auditor') . ':') !!}
            <select name="auditor_id" id="escalation_auditor_id" class="form-control" style="width: 100%;" @if(!auth()->user()->can('crm.escalation.assign_auditor') && !empty($escalation)) disabled @endif>
                @if(!empty($escalation) && !empty($escalation->auditor))
                    <option value="{{ $escalation->auditor_id }}" selected>{{ trim($escalation->auditor->surname . ' ' . $escalation->auditor->first_name . ' ' . $escalation->auditor->last_name) }}</option>
                @endif
            </select>
            @if(!auth()->user()->can('crm.escalation.assign_auditor') && !empty($escalation) && !empty($escalation->auditor_id))
                <input type="hidden" name="auditor_id" value="{{ $escalation->auditor_id }}">
            @endif
        </div>
    </div>
    @endif
</div>
