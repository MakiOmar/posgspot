<?php

namespace Modules\InstallmentCredit\Http\Controllers;

use App\Account;
use App\BusinessLocation;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\InstallmentCredit\Entities\InstallmentCompany;
use Modules\InstallmentCredit\Entities\InstallmentReceivable;
use Modules\InstallmentCredit\Http\Controllers\Concerns\AuthorizesInstallmentModule;
use Modules\InstallmentCredit\Utils\InstallmentCreditUtil;
use Yajra\DataTables\Facades\DataTables;

class ReceivableController extends Controller
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
        $business_id = $this->assertModuleAllowed('installment.view');

        if ($request->ajax()) {
            $query = InstallmentReceivable::with(['company', 'location', 'transaction'])
                ->where('business_id', $business_id)
                ->where('status', 'pending');

            if ($request->filled('company_id')) {
                $query->where('company_id', $request->company_id);
            }
            if ($request->filled('location_id')) {
                $query->where('location_id', $request->location_id);
            }
            if ($request->filled('aging')) {
                $this->installmentUtil->applyAgingBucketFilter($query, $request->aging);
            }
            if ($request->get('due_status') === 'overdue') {
                $query->whereDate('due_date', '<', Carbon::today());
            } elseif ($request->get('due_status') === 'current') {
                $query->where(function ($q) {
                    $q->whereNull('due_date')
                        ->orWhereDate('due_date', '>=', Carbon::today());
                });
            }

            return DataTables::of($query)
                ->addColumn('mass_select', function ($row) {
                    return '<input type="checkbox" class="row-select" value="'.$row->id.'" data-due="'.$row->outstanding.'" data-company="'.$row->company_id.'">';
                })
                ->addColumn('company_name', fn ($row) => $row->company->name ?? '')
                ->addColumn('location_name', fn ($row) => $row->location->name ?? '')
                ->editColumn('due_amount', fn ($row) => $this->commonUtil->num_f($row->due_amount))
                ->addColumn('outstanding', fn ($row) => $this->commonUtil->num_f($row->outstanding))
                ->editColumn('invoice_date', fn ($row) => $row->invoice_date ? $this->commonUtil->format_date($row->invoice_date) : '')
                ->editColumn('due_date', fn ($row) => $row->due_date ? $this->commonUtil->format_date($row->due_date) : '')
                ->addColumn('days_due', function ($row) {
                    $days = (int) $row->days_due;
                    if ($days > 0) {
                        return '<span class="text-danger">'.$days.'</span>';
                    }

                    return (string) $days;
                })
                ->orderColumn('days_due', function ($query, $order) {
                    $dir = strtolower((string) $order) === 'desc' ? 'DESC' : 'ASC';
                    $query->orderByRaw('DATEDIFF(CURDATE(), installment_receivables.due_date) '.$dir);
                })
                ->addColumn('action', function ($row) {
                    $can_delete = auth()->user()->can('superadmin')
                        || auth()->user()->can('installment.settle')
                        || auth()->user()->can('installment.import');
                    if (! $can_delete) {
                        return '';
                    }

                    return '<button type="button" data-href="'.action([ReceivableController::class, 'destroy'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error delete-receivable">'.__('messages.delete').'</button>';
                })
                ->setRowClass(function ($row) {
                    if (! empty($row->due_date) && Carbon::parse($row->due_date)->lt(Carbon::today())) {
                        return 'danger';
                    }

                    return '';
                })
                ->rawColumns(['mass_select', 'action', 'days_due'])
                ->make(true);
        }

        $companies = InstallmentCompany::forDropdown($business_id, false);
        $locations = BusinessLocation::forDropdown($business_id);
        $due_status = $request->get('due_status', '');
        $can_add = auth()->user()->can('superadmin')
            || auth()->user()->can('installment.settle')
            || auth()->user()->can('installment.import');

        return view('installmentcredit::receivables.index', compact('companies', 'locations', 'can_add', 'due_status'));
    }

    /**
     * Modal form: add a pending receivable manually.
     */
    public function create()
    {
        $business_id = $this->assertModuleAllowedAny(['installment.settle', 'installment.import']);
        $companies = InstallmentCompany::forDropdown($business_id, true);
        $locations = BusinessLocation::forDropdown($business_id);

        return view('installmentcredit::receivables.create', compact('companies', 'locations'));
    }

    public function store(Request $request)
    {
        $business_id = $this->assertModuleAllowedAny(['installment.settle', 'installment.import']);

        try {
            $request->validate([
                'company_id' => 'required|integer',
                'due_amount' => 'required',
                'location_id' => 'nullable|integer',
                'invoice_no' => 'nullable|string|max:191',
                'invoice_date' => 'nullable',
                'due_date' => 'nullable',
                'notes' => 'nullable|string',
            ]);

            $recv = $this->installmentUtil->createManualPendingReceivable($business_id, [
                'company_id' => (int) $request->company_id,
                'location_id' => $request->location_id ?: null,
                'invoice_no' => $request->invoice_no,
                'invoice_date' => $request->filled('invoice_date') ? $this->commonUtil->uf_date($request->invoice_date) : null,
                'due_date' => $request->filled('due_date') ? $this->commonUtil->uf_date($request->due_date) : null,
                'due_amount' => $this->commonUtil->num_uf($request->due_amount),
                'notes' => $request->notes ?: 'Manual entry',
            ], false);

            $output = [
                'success' => true,
                'msg' => __('lang_v1.success'),
                'id' => $recv->id,
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().' Line:'.$e->getLine().' Message:'.$e->getMessage());
            $output = [
                'success' => false,
                'msg' => $e->getMessage(),
            ];
        }

        if ($request->ajax()) {
            return $output;
        }

        return redirect('/installment-credit/receivables')->with('status', $output);
    }

    /**
     * Delete a pending receivable that has not been (partially) settled.
     */
    public function destroy($id)
    {
        $business_id = $this->assertModuleAllowedAny(['installment.settle', 'installment.import']);

        try {
            $recv = InstallmentReceivable::where('business_id', $business_id)
                ->where('status', 'pending')
                ->findOrFail($id);

            if ((float) $recv->booked_settled_amount > 0.0001 || $recv->settlementLines()->exists()) {
                throw new \Exception(__('installmentcredit::lang.cannot_delete_receivable_with_settlement'));
            }

            $recv->delete();

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

        return redirect('/installment-credit/receivables')->with('status', [
            'success' => ! empty($output['success']) ? 1 : 0,
            'msg' => $output['msg'],
        ]);
    }

    public function createSettlement(Request $request)
    {
        $business_id = $this->assertModuleAllowed('installment.settle');

        $ids = array_filter(explode(',', (string) $request->get('ids', '')));
        $receivables = InstallmentReceivable::with('company')
            ->where('business_id', $business_id)
            ->where('status', 'pending')
            ->whereIn('id', $ids)
            ->get();

        if ($receivables->isEmpty()) {
            abort(404);
        }

        $company_ids = $receivables->pluck('company_id')->unique();
        if ($company_ids->count() > 1) {
            return redirect()->back()->with('status', [
                'success' => 0,
                'msg' => __('installmentcredit::lang.settle_one_company_only'),
            ]);
        }

        $company = $receivables->first()->company;
        $accounts = Account::forDropdown($business_id, true, false);
        $locations = BusinessLocation::forDropdown($business_id, true);

        // Auto-fill location only when every selected invoice shares the same branch.
        $location_ids = $receivables->pluck('location_id')->unique()->filter(fn ($id) => ! empty($id))->values();
        $default_location_id = $location_ids->count() === 1 ? $location_ids->first() : null;

        return view('installmentcredit::receivables.settle', compact(
            'receivables',
            'company',
            'accounts',
            'locations',
            'default_location_id'
        ));
    }

    public function storeSettlement(Request $request)
    {
        $business_id = $this->assertModuleAllowed('installment.settle');

        try {
            $request->validate([
                'company_id' => 'required|integer',
                'settlement_date' => 'required',
                'account_id' => 'nullable|integer',
                'lines' => 'required|array|min:1',
                'lines.*.receivable_id' => 'required|integer',
                'lines.*.amount_booked' => 'required',
                'lines.*.amount_received' => 'required',
            ]);

            $lines = [];
            foreach ($request->input('lines') as $line) {
                $lines[] = [
                    'receivable_id' => (int) $line['receivable_id'],
                    'amount_booked' => $this->commonUtil->num_uf($line['amount_booked']),
                    'amount_received' => $this->commonUtil->num_uf($line['amount_received']),
                ];
            }

            $settlement = $this->installmentUtil->settle([
                'business_id' => $business_id,
                'company_id' => (int) $request->company_id,
                'location_id' => $request->location_id ?: null,
                'settlement_date' => $this->commonUtil->uf_date($request->settlement_date),
                'account_id' => $request->account_id ?: null,
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
}
