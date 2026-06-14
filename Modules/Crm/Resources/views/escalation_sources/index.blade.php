@extends('layouts.app')
@section('title', __('crm::lang.escalation_sources'))

@section('content')
@include('crm::layouts.nav')

<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('crm::lang.escalation_sources')</h1>
</section>

<section class="content">
    @component('components.widget', ['class' => 'box-primary', 'title' => __('crm::lang.escalation_sources')])
        @slot('tool')
            <div class="box-tools">
                <button type="button" class="tw-dw-btn tw-dw-btn-primary tw-text-white btn-modal pull-right"
                    data-href="{{ action([\Modules\Crm\Http\Controllers\EscalationSourceController::class, 'create']) }}"
                    data-container=".escalation_source_modal">
                    <i class="fa fa-plus"></i> @lang('crm::lang.add_escalation_source')
                </button>
            </div>
        @endslot
        <table class="table table-bordered table-striped" id="escalation_sources_table" style="width: 100%;">
            <thead>
                <tr>
                    <th>@lang('lang_v1.name')</th>
                    <th>@lang('sale.status')</th>
                    <th>@lang('messages.action')</th>
                </tr>
            </thead>
        </table>
    @endcomponent
    <div class="modal fade escalation_source_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
</section>
@endsection

@section('javascript')
<script type="text/javascript">
    $(document).ready(function() {
        escalation_sources_table = $('#escalation_sources_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ action([\Modules\Crm\Http\Controllers\EscalationSourceController::class, "index"]) }}',
            columns: [
                { data: 'name', name: 'name' },
                { data: 'is_active', name: 'is_active' },
                { data: 'action', name: 'action', searchable: false, orderable: false },
            ],
        });

        $(document).on('submit', 'form#escalation_source_form', function(e) {
            e.preventDefault();
            var form = $(this);
            form.find('button[type="submit"]').attr('disabled', true);
            $.ajax({
                method: form.attr('method'),
                url: form.attr('action'),
                dataType: 'json',
                data: form.serialize(),
                success: function(result) {
                    form.find('button[type="submit"]').attr('disabled', false);
                    if (result.success) {
                        $('.escalation_source_modal').modal('hide');
                        toastr.success(result.msg);
                        escalation_sources_table.ajax.reload();
                    } else {
                        toastr.error(result.msg);
                    }
                },
                error: function() {
                    form.find('button[type="submit"]').attr('disabled', false);
                    toastr.error(LANG.something_went_wrong);
                }
            });
        });

        $(document).on('click', '.delete_escalation_source_button', function(e) {
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
                                escalation_sources_table.ajax.reload();
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
