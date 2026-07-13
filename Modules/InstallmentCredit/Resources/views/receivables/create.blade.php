<div class="modal-dialog" role="document">
    <div class="modal-content">
        {!! Form::open(['url' => action([\Modules\InstallmentCredit\Http\Controllers\ReceivableController::class, 'store']), 'method' => 'post', 'id' => 'manual_receivable_form']) !!}
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title">@lang('installmentcredit::lang.add_pending')</h4>
        </div>
        <div class="modal-body">
            <div class="form-group">
                {!! Form::label('company_id', __('installmentcredit::lang.company') . ':*') !!}
                {!! Form::select('company_id', $companies, null, ['class' => 'form-control select2', 'required', 'style' => 'width:100%', 'placeholder' => __('messages.please_select')]) !!}
            </div>
            <div class="form-group">
                {!! Form::label('location_id', __('purchase.business_location') . ':') !!}
                {!! Form::select('location_id', $locations, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('messages.please_select')]) !!}
            </div>
            <div class="form-group">
                {!! Form::label('invoice_no', __('sale.invoice_no') . ':') !!}
                {!! Form::text('invoice_no', null, ['class' => 'form-control']) !!}
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('invoice_date', __('installmentcredit::lang.invoice_date') . ':') !!}
                        {!! Form::text('invoice_date', @format_date('now'), ['class' => 'form-control', 'readonly', 'id' => 'manual_invoice_date']) !!}
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('due_date', __('installmentcredit::lang.due_date') . ':') !!}
                        {!! Form::text('due_date', null, ['class' => 'form-control', 'readonly', 'id' => 'manual_due_date', 'placeholder' => __('installmentcredit::lang.due_date_auto')]) !!}
                    </div>
                </div>
            </div>
            <div class="form-group">
                {!! Form::label('due_amount', __('installmentcredit::lang.due_amount') . ':*') !!}
                {!! Form::text('due_amount', null, ['class' => 'form-control input_number', 'required']) !!}
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
    var $form = $('#manual_receivable_form');
    if (!$form.length) return;
    $form.find('select.select2').select2({ dropdownParent: $form.closest('.modal') });
    $('#manual_invoice_date, #manual_due_date').datepicker({ autoclose: true, format: datepicker_date_format });
    $form.submit(function(e) {
        e.preventDefault();
        $.ajax({
            method: 'POST',
            url: $form.attr('action'),
            data: $form.serialize(),
            dataType: 'json',
            success: function(result) {
                if (result.success) {
                    toastr.success(result.msg);
                    $('.view_modal').modal('hide');
                    if ($('#receivables_table').length) {
                        $('#receivables_table').DataTable().ajax.reload();
                    }
                } else {
                    toastr.error(result.msg);
                }
            },
            error: function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.msg) ? xhr.responseJSON.msg : LANG.something_went_wrong;
                toastr.error(msg);
            }
        });
    });
})();
</script>
