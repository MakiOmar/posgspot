@extends('layouts.app')

@section('title', __('repair::lang.job_sheets'))

@section('content')
@include('repair::layouts.nav')
<!-- Content Header (Page header) -->
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
    	@lang('repair::lang.job_sheets')
    </h1>
</section>
<!-- Main content -->
<section class="content no-print">
    @component('components.filters', ['title' => __('report.filters'), 'closed' => false])
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('location_id',  __('purchase.business_location') . ':') !!}
                {!! Form::select('location_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('contact_id',  __('role.customer') . ':') !!}
                {!! Form::select('contact_id', $customers, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>
        @if(in_array('service_staff' ,$enabled_modules) && !$is_user_service_staff)
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('technician',  __('repair::lang.technician') . ':') !!}
                    {!! Form::select('technician', $service_staffs, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]); !!}
                </div>
            </div>
        @endif
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_date_range', __('report.date_range') . ':') !!}
                {!! Form::text('sell_list_filter_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly']); !!}
            </div>
        </div>
    @endcomponent
    
    @if($job_sheet_statuses->isEmpty())
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-info">
                    @lang('messages.no_data_found')
                </div>
            </div>
        </div>
    @else
        <div class="row">
            <div class="col-md-12">
                <div class="nav-tabs-custom">
                    <ul class="nav nav-tabs" role="tablist">
                        @foreach($job_sheet_statuses as $status)
                            <li class="{{ $loop->first ? 'active' : '' }}">
                                <a href="#job_sheet_tab_status_{{ $status->id }}"
                                    data-toggle="tab"
                                    data-status-id="{{ $status->id }}"
                                    data-is-completed="{{ (int) $status->is_completed_status }}">
                                    <i class="fas fa-circle" @if(!empty($status->color)) style="color: {{ $status->color }};" @endif></i>
                                    {{ $status->name }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <div class="tab-content">
                        @foreach($job_sheet_statuses as $status)
                            <div class="tab-pane {{ $loop->first ? 'active' : '' }}" id="job_sheet_tab_status_{{ $status->id }}">
                                <div class="row">
                                    <div class="col-md-12 mb-12">
                                        <a type="button" class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right"
                                            href="{{ action([\Modules\Repair\Http\Controllers\JobSheetController::class, 'create']) }}" id="add_job_sheet">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                <path d="M12 5l0 14" />
                                                <path d="M5 12l14 0" />
                                            </svg> @lang('messages.add')
                                        </a>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    @include('repair::job_sheet.partials.table', ['table_id' => 'job_sheet_table_status_' . $status->id])
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif
    <div class="modal fade" id="status_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
</section>
<!-- /.content -->
@stop
@section('javascript')
    <script type="text/javascript">
        $(document).ready(function () {
            var jobSheetTables = {};
            @php
                $jobSheetStatusConfigs = $job_sheet_statuses->map(function ($status) {
                    return [
                        'id' => $status->id,
                        'table_id' => 'job_sheet_table_status_' . $status->id,
                        'is_completed_status' => (int) $status->is_completed_status,
                    ];
                })->values();
            @endphp
            var jobSheetStatusConfigs = @json($jobSheetStatusConfigs);

            function reloadJobSheetTables() {
                $.each(jobSheetTables, function (key, table) {
                    table.ajax.reload();
                });
            }

            jobSheetStatusConfigs.forEach(function (statusConfig) {
                var tableSelector = '#' + statusConfig.table_id;

                if (! $(tableSelector).length) {
                    return;
                }

                jobSheetTables[statusConfig.id] = $(tableSelector).DataTable({
                    processing: true,
                    serverSide: true,
                    fixedHeader: false,
                    ajax: {
                        url: '/repair/job-sheet',
                        data: function (d) {
                            if ($('#sell_list_filter_date_range').val()) {
                                var dateRange = $('#sell_list_filter_date_range').data('daterangepicker');
                                if (dateRange) {
                                    d.start_date = dateRange.startDate.format('YYYY-MM-DD');
                                    d.end_date = dateRange.endDate.format('YYYY-MM-DD');
                                }
                            }
                            d.location_id = $('#location_id').val();
                            d.contact_id = $('#contact_id').val();
                            d.status_id = statusConfig.id;
                            d.is_completed_status = statusConfig.is_completed_status;
                            @if(in_array('service_staff' ,$enabled_modules))
                                d.technician = $('#technician').val();
                            @endif
                        }
                    },
                    columnDefs: [
                        {
                            targets: [0, 4],
                            orderable: false,
                            searchable: false
                        }
                    ],
                    aaSorting: [[2, 'asc']],
                    columns: [
                        { data: 'action', name: 'action' },
                        { data: 'service_type', name: 'service_type' },
                        { data: 'delivery_date', name: 'delivery_date' },
                        { data: 'job_sheet_no', name: 'job_sheet_no' },
                        { data: 'repair_no', name: 'repair_no' },
                        { data: 'status', name: 'rs.name' },
                        @if(in_array('service_staff' ,$enabled_modules))
                            { data: 'technecian', name: 'technecian', searchable: false },
                        @endif
                        { data: 'customer', name: 'contacts.name' },
                        { data: 'contact_id', name: 'contacts.contact_id' },
                        { data: 'mobile', name: 'contacts.mobile' },
                        { data: 'location', name: 'bl.name' },
                        { data: 'brand', name: 'b.name' },
                        { data: 'device', name: 'device.name' },
                        { data: 'device_model', name: 'rdm.name' },
                        { data: 'serial_no', name: 'serial_no' },
                        { data: 'estimated_cost', name: 'estimated_cost' },
                        @if(!empty($repair_settings['job_sheet_custom_field_1']))
                            { data: 'custom_field_1', name: 'repair_job_sheets.custom_field_1' },
                        @endif
                        @if(!empty($repair_settings['job_sheet_custom_field_2']))
                            { data: 'custom_field_2', name: 'repair_job_sheets.custom_field_2' },
                        @endif
                        @if(!empty($repair_settings['job_sheet_custom_field_3']))
                            { data: 'custom_field_3', name: 'repair_job_sheets.custom_field_3' },
                        @endif
                        @if(!empty($repair_settings['job_sheet_custom_field_4']))
                            { data: 'custom_field_4', name: 'repair_job_sheets.custom_field_4' },
                        @endif
                        @if(!empty($repair_settings['job_sheet_custom_field_5']))
                            { data: 'custom_field_5', name: 'repair_job_sheets.custom_field_5' },
                        @endif
                        { data: 'added_by', name: 'added_by', searchable: false },
                        { data: 'created_at', name: 'repair_job_sheets.created_at' }
                    ],
                    fnDrawCallback: function () {
                        __currency_convert_recursively($(tableSelector));
                    }
                });
            });

            $('a[data-toggle="tab"][data-status-id]').on('shown.bs.tab', function (e) {
                var statusId = $(e.target).data('status-id');
                if (statusId && jobSheetTables[statusId]) {
                    jobSheetTables[statusId].columns.adjust();
                }
            });

            $(document).on('click', '#delete_job_sheet', function (e) {
                e.preventDefault();
                var url = $(this).data('href');
                swal({
                    title: LANG.sure,
                    icon: "warning",
                    buttons: true,
                    dangerMode: true,
                }).then((confirmed) => {
                    if (confirmed) {
                        $.ajax({
                            method: 'DELETE',
                            url: url,
                            dataType: 'json',
                            success: function(result) {
                                if (result.success) {
                                    toastr.success(result.msg);
                                    reloadJobSheetTables();
                                } else {
                                    toastr.error(result.msg);
                                }
                            }
                        });
                    }
                });
            });

            @if(auth()->user()->can('job_sheet.create') || auth()->user()->can('job_sheet.edit'))
                $(document).on('click', '.edit_job_sheet_status', function () {
                    var url = $(this).data('href');
                    $.ajax({
                        method: 'GET',
                        url: url,
                        dataType: 'html',
                        success: function(result) {
                            $('#status_modal').html(result).modal('show');
                        }
                    });
                });
            @endif

            $('#status_modal').on('shown.bs.modal', function (e) {

                //initialize editor
                tinymce.init({
                    selector: 'textarea#email_body',
                });

                $('#send_sms').change(function() {
                    if ($(this). is(":checked")) {
                        $('div.sms_body').fadeIn();
                    } else {
                        $('div.sms_body').fadeOut();
                    }
                });

                $('#send_email').change(function() {
                    if ($(this). is(":checked")) {
                        $('div.email_template').fadeIn();
                    } else {
                        $('div.email_template').fadeOut();
                    }
                });

                if ($('#status_id_modal').length) {
                    ;
                    $("#sms_body").val($("#status_id_modal :selected").data('sms_template'));
                    $("#email_subject").val($("#status_id_modal :selected").data('email_subject'));
                    tinymce.activeEditor.setContent($("#status_id_modal :selected").data('email_body'));  
                }

                $('#status_id_modal').on('change', function() {
                    var sms_template = $(this).find(':selected').data('sms_template');
                    var email_subject = $(this).find(':selected').data('email_subject');
                    var email_body = $(this).find(':selected').data('email_body');

                    $("#sms_body").val(sms_template);
                    $("#email_subject").val(email_subject);
                    tinymce.activeEditor.setContent(email_body);

                    if ($('#status_modal .mark-as-complete-btn').length) {
                        if ($(this).find(':selected').data('is_completed_status') == 1) 
                        {
                            $('#status_modal').find('.mark-as-complete-btn').removeClass('hide');
                            $('#status_modal').find('.mark-as-incomplete-btn').addClass('hide');
                        } else {
                            $('#status_modal').find('.mark-as-complete-btn').addClass('hide');
                            $('#status_modal').find('.mark-as-incomplete-btn').removeClass('hide');
                        }
                    }
                });
            });
            
            $('#status_modal').on('hidden.bs.modal', function(){
                tinymce.remove("textarea#email_body");
            });
            
            $(document).on('click', '.update_status_button', function(){
                $('#status_form_redirect').val($(this).data('href'));
            })
            $(document).on('submit', 'form#update_status_form', function(e){
                e.preventDefault();
                var data = $(this).serialize();
                var ladda = Ladda.create(document.querySelector('.ladda-button'));
                ladda.start();
                $.ajax({
                    method: $(this).attr("method"),
                    url: $(this).attr("action"),
                    dataType: "json",
                    data: data,
                    success: function(result){
                        ladda.stop();
                        if(result.success == true){
                            $('#status_modal').modal('hide');
                            if (result.msg) {
                                toastr.success(result.msg);
                            }

                            if ($('#status_form_redirect').val()) {
                                window.location = $('#status_form_redirect').val();
                            }
                            reloadJobSheetTables();
                        } else {
                            toastr.error(result.msg);
                        }
                    }
                });
            });

            $(document).on('change', '#location_id, #contact_id, #technician',  function() {
                reloadJobSheetTables();
            });

            $('#sell_list_filter_date_range').daterangepicker(
                dateRangeSettings,
                function(start, end) {
                    $('#sell_list_filter_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(
                        moment_date_format));
                        reloadJobSheetTables();
                }
            );
            $('#sell_list_filter_date_range').on('cancel.daterangepicker', function(ev, picker) {
                $('#sell_list_filter_date_range').val('');
                reloadJobSheetTables();
            });
        });
    </script>
@endsection