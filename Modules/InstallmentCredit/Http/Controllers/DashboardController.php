<?php

namespace Modules\InstallmentCredit\Http\Controllers;

use App\Utils\ModuleUtil;
use App\Utils\Util;
use Carbon\Carbon;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\InstallmentCredit\Entities\InstallmentReceivable;
use Modules\InstallmentCredit\Http\Controllers\Concerns\AuthorizesInstallmentModule;

class DashboardController extends Controller
{
    use AuthorizesInstallmentModule;

    public function __construct(protected ModuleUtil $moduleUtil, protected Util $commonUtil)
    {
    }

    public function index()
    {
        $business_id = $this->assertModuleAllowed('installment.view');

        $pending_total = (float) InstallmentReceivable::where('business_id', $business_id)
            ->where('status', 'pending')
            ->sum(DB::raw('due_amount - booked_settled_amount'));

        $pending_count = InstallmentReceivable::where('business_id', $business_id)
            ->where('status', 'pending')
            ->count();

        $overdue_count = InstallmentReceivable::where('business_id', $business_id)
            ->where('status', 'pending')
            ->whereDate('due_date', '<', Carbon::today())
            ->count();

        $overdue_total = (float) InstallmentReceivable::where('business_id', $business_id)
            ->where('status', 'pending')
            ->whereDate('due_date', '<', Carbon::today())
            ->sum(DB::raw('due_amount - booked_settled_amount'));

        $by_company = InstallmentReceivable::query()
            ->select('company_id', DB::raw('SUM(due_amount - booked_settled_amount) as pending_total'))
            ->where('business_id', $business_id)
            ->where('status', 'pending')
            ->groupBy('company_id')
            ->with('company')
            ->orderByDesc('pending_total')
            ->limit(10)
            ->get();

        return view('installmentcredit::dashboard.index', compact(
            'pending_total',
            'pending_count',
            'overdue_count',
            'overdue_total',
            'by_company'
        ));
    }
}
