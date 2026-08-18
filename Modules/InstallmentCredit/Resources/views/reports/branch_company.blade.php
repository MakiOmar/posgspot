@extends('layouts.app')
@section('title', __('installmentcredit::lang.branch_company_pending'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('installmentcredit::lang.branch_company_pending')</h1>
</section>
<section class="content">
    <div class="box box-primary">
        <div class="box-body table-responsive">
            {{-- One block per location; every installment company is listed even when the pending total is zero --}}
            <table class="table table-bordered ic-branch-company-table">
                <thead>
                    <tr>
                        <th>@lang('purchase.business_location')</th>
                        <th>@lang('installmentcredit::lang.company')</th>
                        <th>@lang('lang_v1.total')</th>
                        <th>@lang('lang_v1.rows')</th>
                    </tr>
                </thead>
                <tbody>
                    @php $grand = 0; $grand_rows = 0; @endphp
                    @forelse($groups as $group)
                        @php
                            $grand += $group['location_total'];
                            $grand_rows += $group['location_rows'];
                        @endphp
                        <tr class="ic-location-header">
                            <th colspan="4">{{ $group['location_name'] }}</th>
                        </tr>
                        @foreach($group['companies'] as $row)
                            <tr class="ic-company-row">
                                <td></td>
                                <td>{{ $row['company_name'] }}</td>
                                <td>@format_currency($row['pending_total'])</td>
                                <td>{{ $row['rows_count'] }}</td>
                            </tr>
                        @endforeach
                        <tr class="ic-location-subtotal">
                            <th></th>
                            <th>@lang('sale.total')</th>
                            <th>@format_currency($group['location_total'])</th>
                            <th>{{ $group['location_rows'] }}</th>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center">@lang('lang_v1.no_data')</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="2">@lang('sale.total')</th>
                        <th>@format_currency($grand)</th>
                        <th>{{ $grand_rows }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="box-footer">
            <a href="{{ url('/installment-credit/reports') }}" class="tw-dw-btn tw-dw-btn-neutral tw-text-white">@lang('messages.back')</a>
        </div>
    </div>
</section>
<style>
    .ic-branch-company-table .ic-location-header th {
        background: #3c8dbc;
        color: #fff;
        font-size: 15px;
        padding: 10px 12px;
    }
    .ic-branch-company-table .ic-company-row td {
        border-bottom: 3px solid #d2d6de !important;
        padding-top: 12px;
        padding-bottom: 12px;
    }
    .ic-branch-company-table .ic-location-subtotal th {
        background: #f4f4f4;
        border-bottom: 6px solid #3c8dbc !important;
    }
</style>
@endsection
