<?php

namespace Modules\InstallmentCredit\Http\Controllers;

use App\Utils\ModuleUtil;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\InstallmentCredit\Entities\InstallmentReceivable;
use Modules\InstallmentCredit\Http\Controllers\Concerns\AuthorizesInstallmentModule;
use Modules\InstallmentCredit\Utils\InstallmentCreditUtil;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use AuthorizesInstallmentModule;

    public function __construct(
        protected ModuleUtil $moduleUtil,
        protected Util $commonUtil,
        protected InstallmentCreditUtil $installmentUtil
    ) {
    }

    public function index()
    {
        $this->assertModuleAllowed('installment.reports');

        return view('installmentcredit::reports.index');
    }

    public function branchCompany(Request $request)
    {
        $business_id = $this->assertModuleAllowed('installment.reports');
        $groups = $this->installmentUtil->pendingByBranchCompany($business_id);

        if ($request->ajax()) {
            return response()->json(['data' => $groups]);
        }

        return view('installmentcredit::reports.branch_company', compact('groups'));
    }

    public function aging(Request $request)
    {
        $business_id = $this->assertModuleAllowed('installment.reports');
        $rows = $this->installmentUtil->agingByCompany($business_id);

        if ($request->ajax()) {
            return response()->json(['data' => $rows]);
        }

        return view('installmentcredit::reports.aging', compact('rows'));
    }

    public function exportPending()
    {
        $business_id = $this->assertModuleAllowed('installment.reports');

        $filename = 'installment-pending-'.date('Y-m-d').'.csv';

        return new StreamedResponse(function () use ($business_id) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'invoice_date', 'due_date', 'invoice_no', 'branch', 'company',
                'due_amount', 'booked_settled', 'actual_received', 'outstanding', 'status',
            ]);

            InstallmentReceivable::with(['company', 'location'])
                ->where('business_id', $business_id)
                ->where('status', 'pending')
                ->orderBy('due_date')
                ->chunk(200, function ($rows) use ($handle) {
                    foreach ($rows as $row) {
                        fputcsv($handle, [
                            optional($row->invoice_date)->format('Y-m-d'),
                            optional($row->due_date)->format('Y-m-d'),
                            $row->invoice_no,
                            $row->location->name ?? '',
                            $row->company->name ?? '',
                            $row->due_amount,
                            $row->booked_settled_amount,
                            $row->actual_received_amount,
                            $row->outstanding,
                            $row->status,
                        ]);
                    }
                });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
