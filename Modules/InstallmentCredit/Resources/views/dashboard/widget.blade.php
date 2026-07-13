{{-- Home dashboard widget (rendered when home widgets are enabled) --}}
@php
    $business_id = session('user.business_id');
    $pending_total = (float) \Modules\InstallmentCredit\Entities\InstallmentReceivable::where('business_id', $business_id)
        ->where('status', 'pending')
        ->sum(\Illuminate\Support\Facades\DB::raw('due_amount - booked_settled_amount'));
    $overdue_count = \Modules\InstallmentCredit\Entities\InstallmentReceivable::where('business_id', $business_id)
        ->where('status', 'pending')
        ->whereDate('due_date', '<', \Carbon\Carbon::today())
        ->count();
@endphp
<div class="col-md-12">
    <div class="box box-solid box-warning">
        <div class="box-header with-border">
            <h3 class="box-title">
                <a href="{{ url('/installment-credit/dashboard') }}">@lang('installmentcredit::lang.installment_credit')</a>
            </h3>
        </div>
        <div class="box-body">
            <div class="row">
                <div class="col-sm-6">
                    <strong>@lang('installmentcredit::lang.pending_total'):</strong>
                    @format_currency($pending_total)
                </div>
                <div class="col-sm-6">
                    <strong>@lang('installmentcredit::lang.overdue_count'):</strong>
                    {{ $overdue_count }}
                </div>
            </div>
        </div>
    </div>
</div>
