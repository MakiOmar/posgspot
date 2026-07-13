@extends('layouts.app')
@section('title', __('installmentcredit::lang.aging_report'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('installmentcredit::lang.aging_report')</h1>
</section>
<section class="content">
    <div class="box box-primary">
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>@lang('installmentcredit::lang.company')</th>
                        <th>@lang('installmentcredit::lang.aging_30')</th>
                        <th>@lang('installmentcredit::lang.aging_60')</th>
                        <th>@lang('installmentcredit::lang.aging_90')</th>
                        <th>@lang('installmentcredit::lang.aging_120')</th>
                        <th>@lang('installmentcredit::lang.aging_gt120')</th>
                        <th>@lang('sale.total')</th>
                    </tr>
                </thead>
                <tbody>
                    @php $t30=$t60=$t90=$t120=$tgt=$tt=0; @endphp
                    @forelse($rows as $row)
                        @php
                            $t30 += $row['d30']; $t60 += $row['d60']; $t90 += $row['d90'];
                            $t120 += $row['d120']; $tgt += $row['d_gt120']; $tt += $row['total'];
                        @endphp
                        <tr>
                            <td>{{ $row['company_name'] }}</td>
                            <td>@format_currency($row['d30'])</td>
                            <td>@format_currency($row['d60'])</td>
                            <td>@format_currency($row['d90'])</td>
                            <td>@format_currency($row['d120'])</td>
                            <td>@format_currency($row['d_gt120'])</td>
                            <td>@format_currency($row['total'])</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center">@lang('lang_v1.no_data')</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <th>@lang('sale.total')</th>
                        <th>@format_currency($t30)</th>
                        <th>@format_currency($t60)</th>
                        <th>@format_currency($t90)</th>
                        <th>@format_currency($t120)</th>
                        <th>@format_currency($tgt)</th>
                        <th>@format_currency($tt)</th>
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
