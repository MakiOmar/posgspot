<?php

namespace Modules\InstallmentCredit\Http\Controllers;

use App\Account;
use App\BusinessLocation;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\InstallmentCredit\Entities\InstallmentSettlement;
use Modules\InstallmentCredit\Http\Controllers\Concerns\AuthorizesInstallmentModule;
use Modules\InstallmentCredit\Utils\InstallmentCreditUtil;
use Yajra\DataTables\Facades\DataTables;

class SettlementController extends Controller
{
    use AuthorizesInstallmentModule;

    public function __construct(
        protected ModuleUtil $moduleUtil,
        protected Util $commonUtil,
        protected InstallmentCreditUtil $installmentUtil
    ) {
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
                    $html = '<div class="btn-group">';
                    $html .= '<a href="'.action([SettlementController::class, 'show'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info">'.__('messages.view').'</a> ';
                    $html .= '<a href="'.action([SettlementController::class, 'edit'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary">'.__('messages.edit').'</a> ';
                    $html .= '<button type="button" data-href="'.action([SettlementController::class, 'destroy'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error delete-settlement">'.__('messages.delete').'</button>';
                    $html .= '</div>';

                    return $html;
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

    public function edit($id)
    {
        $business_id = $this->assertModuleAllowed('installment.settle');
        $settlement = InstallmentSettlement::with(['company', 'lines.receivable'])
            ->where('business_id', $business_id)
            ->findOrFail($id);

        $company = $settlement->company;
        $accounts = Account::forDropdown($business_id, true, false);
        $locations = BusinessLocation::forDropdown($business_id, true);

        // Outstanding as if this settlement were reversed (so current line booked is editable).
        $line_rows = [];
        foreach ($settlement->lines as $line) {
            $recv = $line->receivable;
            $outstanding_if_reversed = $recv
                ? max(0, (float) $recv->due_amount - ((float) $recv->booked_settled_amount - (float) $line->amount_booked))
                : (float) $line->amount_booked;
            $line_rows[] = [
                'line' => $line,
                'receivable' => $recv,
                'outstanding' => $outstanding_if_reversed,
            ];
        }

        return view('installmentcredit::settlements.edit', compact(
            'settlement',
            'company',
            'accounts',
            'locations',
            'line_rows'
        ));
    }

    public function update(Request $request, $id)
    {
        $business_id = $this->assertModuleAllowed('installment.settle');

        try {
            $request->validate([
                'settlement_date' => 'required',
                'account_id' => 'nullable|integer',
                'lines' => 'required|array|min:1',
                'lines.*.receivable_id' => 'required|integer',
                'lines.*.amount_booked' => 'required',
                'lines.*.amount_received' => 'required',
            ]);

            $settlement = InstallmentSettlement::where('business_id', $business_id)->findOrFail($id);

            $lines = [];
            foreach ($request->input('lines') as $line) {
                $lines[] = [
                    'receivable_id' => (int) $line['receivable_id'],
                    'amount_booked' => $this->commonUtil->num_uf($line['amount_booked']),
                    'amount_received' => $this->commonUtil->num_uf($line['amount_received']),
                ];
            }

            $settlement = $this->installmentUtil->updateSettlement($settlement, [
                'settlement_date' => $this->commonUtil->uf_date($request->settlement_date),
                'account_id' => $request->account_id ?: null,
                'location_id' => $request->location_id ?: null,
                'external_ref' => $request->external_ref,
                'notes' => $request->notes,
            ], $lines, auth()->user()->id);

            return redirect('/installment-credit/settlements/'.$settlement->id)
                ->with('status', ['success' => 1, 'msg' => __('lang_v1.success')]);
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().' Line:'.$e->getLine().' Message:'.$e->getMessage());

            return redirect()->back()->withInput()->with('status', [
                'success' => 0,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    public function destroy($id)
    {
        $business_id = $this->assertModuleAllowed('installment.settle');

        try {
            $settlement = InstallmentSettlement::where('business_id', $business_id)->findOrFail($id);
            $this->installmentUtil->reverseSettlement($settlement, true);

            $output = [
                'success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().' Line:'.$e->getLine().' Message:'.$e->getMessage());
            $output = [
                'success' => false,
                'msg' => $e->getMessage(),
            ];
        }

        if (request()->ajax()) {
            return response()->json($output);
        }

        return redirect('/installment-credit/settlements')->with('status', [
            'success' => $output['success'] ? 1 : 0,
            'msg' => $output['msg'],
        ]);
    }
}
