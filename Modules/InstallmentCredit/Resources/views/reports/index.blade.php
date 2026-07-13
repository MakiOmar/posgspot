@extends('layouts.app')
@section('title', __('installmentcredit::lang.reports'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('installmentcredit::lang.reports')</h1>
</section>
<section class="content">
    <div class="row">
        <div class="col-md-4">
            <a href="{{ url('/installment-credit/reports/branch-company') }}" class="btn btn-app btn-block" style="height:auto;padding:20px">
                <i class="fa fa-sitemap"></i> @lang('installmentcredit::lang.branch_company_pending')
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ url('/installment-credit/reports/aging') }}" class="btn btn-app btn-block" style="height:auto;padding:20px">
                <i class="fa fa-clock"></i> @lang('installmentcredit::lang.aging_report')
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ url('/installment-credit/reports/export-pending') }}" class="btn btn-app btn-block" style="height:auto;padding:20px">
                <i class="fa fa-download"></i> @lang('installmentcredit::lang.export_pending')
            </a>
        </div>
    </div>
</section>
@endsection
