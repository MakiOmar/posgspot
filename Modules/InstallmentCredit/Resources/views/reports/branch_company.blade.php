@extends('layouts.app')
@section('title', __('installmentcredit::lang.branch_company_pending'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('installmentcredit::lang.branch_company_pending')</h1>
</section>
<section class="content">
    <div class="box box-primary">
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>@lang('purchase.business_location')</th>
                        <th>@lang('installmentcredit::lang.company')</th>
                        <th>@lang('lang_v1.total')</th>
                        <th>@lang('lang_v1.rows')</th>
                    </tr>
                </thead>
                <tbody>
                    @php $grand = 0; @endphp
                    @forelse($rows as $row)
                        @php $grand += $row->pending_total; @endphp
                        <tr>
                            <td>{{ $row->location->name ?? '-' }}</td>
                            <td>{{ $row->company->name ?? '-' }}</td>
                            <td>@format_currency($row->pending_total)</td>
                            <td>{{ $row->rows_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center">@lang('lang_v1.no_data')</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="2">@lang('sale.total')</th>
                        <th>@format_currency($grand)</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="box-footer">
            <a href="{{ url('/installment-credit/reports') }}" class="tw-dw-btn tw-dw-btn-neutral tw-text-white">@lang('messages.back')</a>
        </div>
    </div>
</section>
@endsection
