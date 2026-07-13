<?php

namespace Modules\InstallmentCredit\Http\Controllers;

use App\System;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\InstallmentCredit\Entities\InstallmentCompany;

/**
 * First-party module installer (no Codecanyon license gate).
 */
class InstallController extends Controller
{
    protected $module_name = 'installmentcredit';

    protected $module_display_name = 'Installment Credit';

    public function __construct(protected ModuleUtil $moduleUtil)
    {
    }

    public function index()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $is_installed = System::getProperty($this->module_name.'_version');
        if (! empty($is_installed)) {
            return redirect('/home')->with('status', [
                'success' => 1,
                'msg' => 'Installment Credit module is already installed.',
            ]);
        }

        $action_url = action([InstallController::class, 'install']);
        $module_display_name = $this->module_display_name;

        return view('installmentcredit::install', compact('action_url', 'module_display_name'));
    }

    public function install(Request $request)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            DB::beginTransaction();

            $is_installed = System::getProperty($this->module_name.'_version');
            if (! empty($is_installed)) {
                abort(404);
            }

            DB::statement('SET default_storage_engine=INNODB;');
            Artisan::call('module:migrate', ['module' => 'InstallmentCredit', '--force' => true]);
            System::addProperty($this->module_name.'_version', config('installmentcredit.module_version'));

            $business_id = $request->session()->get('user.business_id');
            if (! empty($business_id)) {
                InstallmentCompany::seedDefaultsForBusiness($business_id);
            }

            DB::commit();

            return redirect('/installment-credit/companies')->with('status', [
                'success' => 1,
                'msg' => __('installmentcredit::lang.installed_success'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().' Line:'.$e->getLine().' Message:'.$e->getMessage());

            return redirect()->back()->with('status', [
                'success' => 0,
                'msg' => $e->getMessage(),
            ]);
        }
    }
}
