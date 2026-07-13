<?php

namespace Modules\InstallmentCredit\Http\Controllers;

use App\Utils\ModuleUtil;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\InstallmentCredit\Entities\InstallmentSettlement;
use Modules\InstallmentCredit\Http\Controllers\Concerns\AuthorizesInstallmentModule;
use Yajra\DataTables\Facades\DataTables;

class SettlementController extends Controller
{
    use AuthorizesInstallmentModule;

    public function __construct(protected ModuleUtil $moduleUtil, protected Util $commonUtil)
    {
    }

    public function index(Request $request)
    {
        $business_id = $this->assertModuleAllowed('installment.settle');

        if ($request->ajax()) {
            $query = InstallmentSettlement::with(['company', 'creator'])
                ->where('business_id', $business_id);

            return DataTables::of($query)
                ->editColumn('settlement_date', fn ($row) => $this->commonUtil->format_date($row->settlement_date))
                ->addColumn('company_name', fn ($row) => $row->company->name ?? '')
                ->editColumn('amount_booked', fn ($row) => $this->commonUtil->num_f($row->amount_booked))
                ->editColumn('amount_received', fn ($row) => $this->commonUtil->num_f($row->amount_received))
                ->editColumn('fee_amount', fn ($row) => $this->commonUtil->num_f($row->fee_amount))
                ->addColumn('created_by_name', fn ($row) => $row->creator->user_full_name ?? '')
                ->addColumn('action', function ($row) {
                    return '<a href="'.action([SettlementController::class, 'show'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info">'.__('messages.view').'</a>';
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('installmentcredit::settlements.index');
    }

    public function show($id)
    {
        $business_id = $this->assertModuleAllowed('installment.settle');
        $settlement = InstallmentSettlement::with(['company', 'lines.receivable', 'account', 'creator'])
            ->where('business_id', $business_id)
            ->findOrFail($id);

        return view('installmentcredit::settlements.show', compact('settlement'));
    }
}
