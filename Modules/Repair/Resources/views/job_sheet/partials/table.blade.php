<table class="table table-bordered table-striped" id="{{ $table_id }}">
    <thead>
        <tr>
            <th>@lang('messages.action')</th>
            <th>@lang('repair::lang.service_type')</th>
            <th>@lang('lang_v1.due_date')</th>
            <th>@lang('repair::lang.job_sheet_no')</th>
            <th>@lang('sale.invoice_no')</th>
            <th>@lang('sale.status')</th>
            @if(in_array('service_staff' ,$enabled_modules))
                <th>@lang('repair::lang.technician')</th>
            @endif
            <th>@lang('role.customer')</th>
            <th>@lang('lang_v1.contact_id')</th>
            <th>@lang('repair::lang.customer_phone')</th>
            <th>@lang('business.location')</th>
            <th>@lang('product.brand')</th>
            <th>@lang('repair::lang.device')</th>
            <th>@lang('repair::lang.device_model')</th>
            <th>@lang('repair::lang.serial_no')</th>
            <th>@lang('repair::lang.estimated_cost')</th>
            @if(!empty($repair_settings['job_sheet_custom_field_1']))
                <th>{{ $repair_settings['job_sheet_custom_field_1'] }}</th>
            @endif
            @if(!empty($repair_settings['job_sheet_custom_field_2']))
                <th>{{ $repair_settings['job_sheet_custom_field_2'] }}</th>
            @endif
            @if(!empty($repair_settings['job_sheet_custom_field_3']))
                <th>{{ $repair_settings['job_sheet_custom_field_3'] }}</th>
            @endif
            @if(!empty($repair_settings['job_sheet_custom_field_4']))
                <th>{{ $repair_settings['job_sheet_custom_field_4'] }}</th>
            @endif
            @if(!empty($repair_settings['job_sheet_custom_field_5']))
                <th>{{ $repair_settings['job_sheet_custom_field_5'] }}</th>
            @endif
            <th>@lang('lang_v1.added_by')</th>
            <th>@lang('lang_v1.created_at')</th>
        </tr>
    </thead>
</table>

