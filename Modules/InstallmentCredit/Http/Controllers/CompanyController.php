<?php

namespace Modules\InstallmentCredit\Http\Controllers;

use App\Account;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\InstallmentCredit\Entities\InstallmentCompany;
use Modules\InstallmentCredit\Http\Controllers\Concerns\AuthorizesInstallmentModule;
use Yajra\DataTables\Facades\DataTables;

class CompanyController extends Controller
{
    use AuthorizesInstallmentModule;

    public function __construct(protected ModuleUtil $moduleUtil, protected Util $commonUtil)
    {
    }

    public function index(Request $request)
    {
        $business_id = $this->assertModuleAllowed('installment.companies');

        if ($request->ajax()) {
            $companies = InstallmentCompany::where('business_id', $business_id)->select('*');

            return DataTables::of($companies)
                ->addColumn('action', function ($row) {
                    $html = '<button data-href="'.action([CompanyController::class, 'edit'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary btn-modal" data-container=".view_modal"><i class="glyphicon glyphicon-edit"></i> '.__('messages.edit').'</button>';
                    $html .= ' <button data-href="'.action([CompanyController::class, 'destroy'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error delete-company"><i class="glyphicon glyphicon-trash"></i> '.__('messages.delete').'</button>';

                    return $html;
                })
                ->editColumn('is_active', function ($row) {
                    return $row->is_active ? __('messages.yes') : __('messages.no');
                })
                ->editColumn('payment_method_key', function ($row) {
                    return $row->payment_method_key ?: '-';
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('installmentcredit::companies.index');
    }

    public function create()
    {
        $business_id = $this->assertModuleAllowed('installment.companies');
        $payment_types = $this->commonUtil->payment_types(null, false, $business_id);
        $accounts = Account::forDropdown($business_id, true, false);

        return view('installmentcredit::companies.create', compact('payment_types', 'accounts'));
    }

    public function store(Request $request)
    {
        $business_id = $this->assertModuleAllowed('installment.companies');

        try {
            $input = $request->only([
                'name', 'code', 'is_active', 'default_settlement_days',
                'fee_mode', 'fee_percent', 'fee_fixed', 'payment_method_key',
                'default_deposit_account_id', 'notes',
            ]);
            $input['business_id'] = $business_id;
            $input['is_active'] = $request->has('is_active') ? 1 : 0;
            $input['code'] = strtolower(trim($input['code']));
            $input['default_settlement_days'] = (int) ($input['default_settlement_days'] ?: 20);
            $input['payment_method_key'] = $input['payment_method_key'] ?: null;
            $input['default_deposit_account_id'] = $input['default_deposit_account_id'] ?: null;

            InstallmentCompany::create($input);

            return [
                'success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().' Line:'.$e->getLine().' Message:'.$e->getMessage());

            return [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }
    }

    public function edit($id)
    {
        $business_id = $this->assertModuleAllowed('installment.companies');
        $company = InstallmentCompany::where('business_id', $business_id)->findOrFail($id);
        $payment_types = $this->commonUtil->payment_types(null, false, $business_id);
        $accounts = Account::forDropdown($business_id, true, false);

        return view('installmentcredit::companies.edit', compact('company', 'payment_types', 'accounts'));
    }

    public function update(Request $request, $id)
    {
        $business_id = $this->assertModuleAllowed('installment.companies');

        try {
            $company = InstallmentCompany::where('business_id', $business_id)->findOrFail($id);
            $input = $request->only([
                'name', 'code', 'default_settlement_days',
                'fee_mode', 'fee_percent', 'fee_fixed', 'payment_method_key',
                'default_deposit_account_id', 'notes',
            ]);
            $input['is_active'] = $request->has('is_active') ? 1 : 0;
            $input['code'] = strtolower(trim($input['code']));
            $input['payment_method_key'] = $input['payment_method_key'] ?: null;
            $input['default_deposit_account_id'] = $input['default_deposit_account_id'] ?: null;
            $company->update($input);

            return [
                'success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().' Line:'.$e->getLine().' Message:'.$e->getMessage());

            return [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }
    }

    public function destroy($id)
    {
        $business_id = $this->assertModuleAllowed('installment.companies');

        try {
            $company = InstallmentCompany::where('business_id', $business_id)->findOrFail($id);
            if ($company->receivables()->where('status', 'pending')->exists()) {
                return [
                    'success' => false,
                    'msg' => __('installmentcredit::lang.cannot_delete_company_with_pending'),
                ];
            }
            $company->delete();

            return [
                'success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().' Line:'.$e->getLine().' Message:'.$e->getMessage());

            return [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }
    }

    public function seedDefaults(Request $request)
    {
        $business_id = $this->assertModuleAllowed('installment.companies');
        InstallmentCompany::seedDefaultsForBusiness($business_id);
        $moved = InstallmentCompany::remapConflictingPaymentMethods($business_id);

        return [
            'success' => true,
            'msg' => __('installmentcredit::lang.defaults_seeded').($moved ? ' '.__('installmentcredit::lang.remapped_slots', ['count' => $moved]) : ''),
        ];
    }

    /**
     * Remap companies away from custom payment slots already labeled for other methods.
     */
    public function remapPayments(Request $request)
    {
        $business_id = $this->assertModuleAllowed('installment.companies');
        $moved = InstallmentCompany::remapConflictingPaymentMethods($business_id);

        return [
            'success' => true,
            'msg' => __('installmentcredit::lang.remapped_slots', ['count' => $moved]),
        ];
    }
}
