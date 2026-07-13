<?php

namespace Modules\InstallmentCredit\Http\Controllers\Concerns;

use App\Utils\ModuleUtil;

trait AuthorizesInstallmentModule
{
    protected function assertModuleAllowed($permission = null)
    {
        $business_id = request()->session()->get('user.business_id');
        $moduleUtil = $this->moduleUtil ?? new ModuleUtil();

        if (! $moduleUtil->isModuleInstalled('InstallmentCredit')) {
            abort(403, 'Installment Credit module is not installed.');
        }

        if (auth()->user()->can('superadmin')) {
            return $business_id;
        }

        if ($permission && ! auth()->user()->can($permission)) {
            abort(403, 'Unauthorized action.');
        }

        return $business_id;
    }

    /**
     * @param  string|array  $permissions
     */
    protected function assertModuleAllowedAny($permissions)
    {
        $business_id = request()->session()->get('user.business_id');
        $moduleUtil = $this->moduleUtil ?? new ModuleUtil();

        if (! $moduleUtil->isModuleInstalled('InstallmentCredit')) {
            abort(403, 'Installment Credit module is not installed.');
        }

        if (auth()->user()->can('superadmin')) {
            return $business_id;
        }

        $permissions = (array) $permissions;
        foreach ($permissions as $permission) {
            if (auth()->user()->can($permission)) {
                return $business_id;
            }
        }

        abort(403, 'Unauthorized action.');
    }
}
