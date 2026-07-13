<?php

use App\Business;
use App\System;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Modules\InstallmentCredit\Entities\InstallmentCompany;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Mark module installed after schema migrations so the admin menu appears
 * after a normal `module:migrate` / `migrate` without visiting /install.
 */
class MarkInstallmentCreditInstalled extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('installment_companies')) {
            return;
        }

        System::updateOrCreate(
            ['key' => 'installmentcredit_version'],
            ['value' => config('installmentcredit.module_version', '1.0')]
        );

        $permissions = [
            'installment.view',
            'installment.companies',
            'installment.settle',
            'installment.reports',
            'installment.import',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Seed default companies + grant Admin roles for every business
        foreach (Business::pluck('id') as $business_id) {
            InstallmentCompany::seedDefaultsForBusiness($business_id);

            $admin = Role::where('name', 'Admin#'.$business_id)->first();
            if ($admin) {
                $admin->givePermissionTo($permissions);
            }
        }
    }

    public function down()
    {
        System::where('key', 'installmentcredit_version')->delete();
    }
}
