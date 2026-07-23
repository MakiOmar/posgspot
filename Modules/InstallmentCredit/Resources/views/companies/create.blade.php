<div class="modal-dialog" role="document">
    <div class="modal-content">
        {!! Form::open(['url' => action([\Modules\InstallmentCredit\Http\Controllers\CompanyController::class, 'store']), 'method' => 'post', 'id' => 'company_add_form']) !!}
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title">@lang('installmentcredit::lang.add_company')</h4>
        </div>
        <div class="modal-body">
            <div class="form-group">
                {!! Form::label('name', __('lang_v1.name') . ':*') !!}
                {!! Form::text('name', null, ['class' => 'form-control', 'required']) !!}
            </div>
            <div class="form-group">
                {!! Form::label('code', __('installmentcredit::lang.code') . ':*') !!}
                {!! Form::text('code', null, ['class' => 'form-control', 'required', 'placeholder' => 'value, maylo, tru…']) !!}
            </div>
            <div class="form-group">
                {!! Form::label('payment_method_key', __('installmentcredit::lang.payment_method_key') . ':') !!}
                {!! Form::select('payment_method_key', $payment_types, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('messages.please_select')]) !!}
            </div>
            <div class="form-group">
                {!! Form::label('default_settlement_days', __('installmentcredit::lang.settlement_days') . ':') !!}
                {!! Form::number('default_settlement_days', 20, ['class' => 'form-control', 'min' => 0]) !!}
            </div>
            <div class="form-group">
                {!! Form::label('default_deposit_account_id', __('installmentcredit::lang.default_deposit_account') . ':') !!}
                {!! Form::select('default_deposit_account_id', $accounts, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('messages.please_select')]) !!}
                <p class="help-block">@lang('installmentcredit::lang.default_deposit_account_help')</p>
            </div>
            <div class="form-group">
                {!! Form::label('fee_mode', __('installmentcredit::lang.fee_mode') . ':') !!}
                {!! Form::select('fee_mode', ['none' => 'None', 'percent' => 'Percent', 'fixed' => 'Fixed'], 'none', ['class' => 'form-control']) !!}
            </div>
            <div class="form-group">
                {!! Form::label('fee_percent', __('installmentcredit::lang.fee_percent') . ':') !!}
                {!! Form::text('fee_percent', null, ['class' => 'form-control']) !!}
            </div>
            <div class="form-group">
                {!! Form::label('fee_fixed', __('installmentcredit::lang.fee_fixed') . ':') !!}
                {!! Form::text('fee_fixed', null, ['class' => 'form-control']) !!}
            </div>
            <div class="checkbox">
                <label>{!! Form::checkbox('is_active', 1, true) !!} @lang('installmentcredit::lang.is_active')</label>
            </div>
            <div class="form-group">
                {!! Form::label('notes', __('lang_v1.notes') . ':') !!}
                {!! Form::textarea('notes', null, ['class' => 'form-control', 'rows' => 2]) !!}
            </div>
        </div>
        <div class="modal-footer">
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('messages.save')</button>
            <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">@lang('messages.close')</button>
        </div>
        {!! Form::close() !!}
    </div>
</div>
<script>
(function() {
    if ($('#company_add_form').length) {
        $('select.select2').select2({ dropdownParent: $('#company_add_form').closest('.modal') });
        $('#company_add_form').submit(function(e) {
            e.preventDefault();
            var form = $(this);
            $.ajax({
                method: 'POST',
                url: form.attr('action'),
                data: form.serialize(),
                dataType: 'json',
                success: function(result) {
                    if (result.success) {
                        toastr.success(result.msg);
                        $('.view_modal').modal('hide');
                        if ($('#companies_table').length) {
                            $('#companies_table').DataTable().ajax.reload();
                        }
                    } else {
                        toastr.error(result.msg);
                    }
                }
            });
        });
    }
})();
</script>
