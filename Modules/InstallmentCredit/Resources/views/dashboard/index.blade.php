@extends('layouts.app')
@section('title', __('installmentcredit::lang.dashboard'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('installmentcredit::lang.dashboard')</h1>
</section>
<section class="content">
    <div class="row">
        <div class="col-md-3 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-aqua"><i class="fa fa-money-bill"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">@lang('installmentcredit::lang.pending_total')</span>
                    <span class="info-box-number">@format_currency($pending_total)</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-yellow"><i class="fa fa-file-invoice"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">@lang('installmentcredit::lang.pending_count')</span>
                    <span class="info-box-number">{{ $pending_count }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-red"><i class="fa fa-exclamation-triangle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">@lang('installmentcredit::lang.overdue_count')</span>
                    <span class="info-box-number">{{ $overdue_count }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-maroon"><i class="fa fa-clock"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">@lang('installmentcredit::lang.overdue_total')</span>
                    <span class="info-box-number">@format_currency($overdue_total)</span>
                </div>
            </div>
        </div>
    </div>

    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('installmentcredit::lang.companies')</h3>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>@lang('installmentcredit::lang.company')</th>
                        <th>@lang('installmentcredit::lang.pending_total')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($by_company as $row)
                        <tr>
                            <td>{{ $row->company->name ?? '-' }}</td>
                            <td>@format_currency($row->pending_total)</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="text-center">@lang('lang_v1.no_data')</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="box-footer">
            <a href="{{ url('/installment-credit/receivables') }}" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('installmentcredit::lang.pending_receivables')</a>
            <a href="{{ url('/installment-credit/reports') }}" class="tw-dw-btn tw-dw-btn-info tw-text-white">@lang('installmentcredit::lang.reports')</a>
        </div>
    </div>
</section>
@endsection
