@extends('layouts.app')
@section('title', __('installmentcredit::lang.settlements'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('installmentcredit::lang.settlements')</h1>
</section>
<section class="content">
    @component('components.widget', ['class' => 'box-primary'])
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="settlements_table" width="100%">
                <thead>
                    <tr>
                        <th>@lang('messages.action')</th>
                        <th>@lang('installmentcredit::lang.settlement_date')</th>
                        <th>@lang('installmentcredit::lang.company')</th>
                        <th>@lang('installmentcredit::lang.amount_booked')</th>
                        <th>@lang('installmentcredit::lang.amount_received')</th>
                        <th>@lang('installmentcredit::lang.fee_amount')</th>
                        <th>@lang('installmentcredit::lang.external_ref')</th>
                        <th>@lang('lang_v1.added_by')</th>
                    </tr>
                </thead>
            </table>
        </div>
    @endcomponent
</section>
@endsection

@section('javascript')
<script>
$(document).ready(function() {
    var table = $('#settlements_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ url("/installment-credit/settlements") }}',
        columns: [
            { data: 'action', orderable: false, searchable: false },
            { data: 'settlement_date', name: 'settlement_date' },
            { data: 'company_name', name: 'company_name' },
            { data: 'amount_booked', name: 'amount_booked' },
            { data: 'amount_received', name: 'amount_received' },
            { data: 'fee_amount', name: 'fee_amount' },
            { data: 'external_ref', name: 'external_ref' },
            { data: 'created_by_name', name: 'created_by_name' }
        ]
    });

    $(document).on('click', '.delete-settlement', function(e) {
        e.preventDefault();
        var url = $(this).data('href');
        swal({
            title: LANG.sure,
            text: {!! json_encode(__('installmentcredit::lang.delete_settlement_confirm')) !!},
            icon: 'warning',
            buttons: true,
            dangerMode: true,
        }).then(function(willDelete) {
            if (willDelete) {
                $.ajax({
                    method: 'DELETE',
                    url: url,
                    dataType: 'json',
                    success: function(result) {
                        if (result.success) {
                            toastr.success(result.msg);
                            table.ajax.reload();
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
