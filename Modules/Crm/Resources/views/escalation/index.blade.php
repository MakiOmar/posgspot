@extends('layouts.app')
@section('title', __('crm::lang.escalations'))

@section('content')
@include('crm::layouts.nav')

<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        @lang('crm::lang.escalations')
        @can('crm.escalation.create')
            <div class="pull-right">
                <a href="{{ action([\Modules\Crm\Http\Controllers\EscalationController::class, 'create']) }}" class="tw-dw-btn tw-dw-btn-primary tw-text-white">
                    <i class="fa fa-plus"></i> @lang('crm::lang.add_escalation')
                </a>
            </div>
        @endcan
    </h1>
</section>

<section class="content no-print">
    @component('components.filters', ['title' => __('report.filters')])
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('filter_employee_id', __('crm::lang.escalation_employee') . ':') !!}
                    {!! Form::select('filter_employee_id', $users, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'filter_employee_id', 'placeholder' => __('messages.all')]) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('filter_observer_id', __('crm::lang.escalation_observer') . ':') !!}
                    {!! Form::select('filter_observer_id', $users, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'filter_observer_id', 'placeholder' => __('messages.all')]) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('filter_auditor_id', __('crm::lang.escalation_auditor') . ':') !!}
                    {!! Form::select('filter_auditor_id', $users, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'filter_auditor_id', 'placeholder' => __('messages.all')]) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('filter_location_id', __('business.business_location') . ':') !!}
                    {!! Form::select('filter_location_id', $locations, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'filter_location_id', 'placeholder' => __('messages.all')]) !!}
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('filter_source_id', __('crm::lang.escalation_taken_by') . ':') !!}
                    {!! Form::select('filter_source_id', $sources, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'filter_source_id', 'placeholder' => __('messages.all')]) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('filter_status', __('sale.status') . ':') !!}
                    {!! Form::select('filter_status', $statuses, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'filter_status', 'placeholder' => __('messages.all')]) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('escalation_date_range', __('report.date_range') . ':') !!}
                    {!! Form::text('escalation_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly', 'id' => 'escalation_date_range']) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <br>
                    <label>
                        {!! Form::checkbox('filter_callbacks_due', 1, false, ['id' => 'filter_callbacks_due', 'class' => 'input-icheck']) !!}
                        @lang('crm::lang.escalation_callbacks_due')
                    </label>
                </div>
            </div>
        </div>
    @endcomponent

    @component('components.widget', ['class' => 'box-solid'])
        <table class="table table-bordered table-striped" id="escalations_table" style="width: 100%;">
            <thead>
                <tr>
                    <th>@lang('crm::lang.escalation_reference_no')</th>
                    <th>@lang('crm::lang.escalation_escalated_at')</th>
                    <th>@lang('contact.customer')</th>
                    <th>@lang('crm::lang.escalation_phone')</th>
                    <th>@lang('crm::lang.description')</th>
                    <th>@lang('crm::lang.escalation_taken_by')</th>
                    <th>@lang('business.business_location')</th>
                    <th>@lang('crm::lang.escalation_invoice')</th>
                    <th>@lang('crm::lang.escalation_employee')</th>
                    <th>@lang('crm::lang.escalation_observer')</th>
                    <th>@lang('crm::lang.escalation_auditor')</th>
                    <th>@lang('sale.status')</th>
                    <th>@lang('messages.action')</th>
                </tr>
            </thead>
        </table>
    @endcomponent
</section>
@endsection

@section('javascript')
<script type="text/javascript">
    $(document).ready(function() {
        $('.select2').select2();

        $('#escalation_date_range').daterangepicker(
            dateRangeSettings,
            function(start, end) {
                $('#escalation_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
                escalations_table.ajax.reload();
            }
        );
        $('#escalation_date_range').on('cancel.daterangepicker', function() {
            $('#escalation_date_range').val('');
            escalations_table.ajax.reload();
        });

        escalations_table = $('#escalations_table').DataTable({
            processing: true,
            serverSide: true,
            scrollY: '75vh',
            scrollX: true,
            scrollCollapse: true,
            fixedHeader: false,
            ajax: {
                url: '{{ action([\Modules\Crm\Http\Controllers\EscalationController::class, "index"]) }}',
                data: function(d) {
                    d.employee_id = $('#filter_employee_id').val();
                    d.observer_id = $('#filter_observer_id').val();
                    d.auditor_id = $('#filter_auditor_id').val();
                    d.location_id = $('#filter_location_id').val();
                    d.source_id = $('#filter_source_id').val();
                    d.status = $('#filter_status').val();
                    d.callbacks_due = $('#filter_callbacks_due').is(':checked') ? 1 : 0;
                    if ($('#escalation_date_range').val()) {
                        d.start_date = $('#escalation_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
                        d.end_date = $('#escalation_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');
                    }
                }
            },
            columns: [
                { data: 'reference_no', name: 'reference_no' },
                { data: 'escalated_at', name: 'escalated_at' },
                { data: 'customer', name: 'customer' },
                { data: 'phone', name: 'phone' },
                { data: 'description', name: 'description' },
                { data: 'source_name', name: 'source_name' },
                { data: 'location_name', name: 'location_name' },
                { data: 'invoice_no', name: 'invoice_no' },
                { data: 'employee_name', name: 'employee_name' },
                { data: 'observer_name', name: 'observer_name' },
                { data: 'auditor_name', name: 'auditor_name' },
                { data: 'status', name: 'status' },
                { data: 'action', name: 'action', searchable: false, orderable: false },
            ],
        });

        $(document).on('change', '#filter_employee_id, #filter_observer_id, #filter_auditor_id, #filter_location_id, #filter_source_id, #filter_status, #filter_callbacks_due', function() {
            escalations_table.ajax.reload();
        });

        $(document).on('click', '.delete_escalation_button', function(e) {
            e.preventDefault();
            var href = $(this).data('href');
            swal({
                title: LANG.sure,
                icon: 'warning',
                buttons: true,
                dangerMode: true,
            }).then(function(willDelete) {
                if (willDelete) {
                    $.ajax({
                        method: 'DELETE',
                        url: href,
                        dataType: 'json',
                        success: function(result) {
                            if (result.success) {
                                toastr.success(result.msg);
                                escalations_table.ajax.reload();
                            } else {
                                toastr.error(result.msg);
                            }
                        }
                    });
                }
            });
        });
    });
</script>
@endsection
