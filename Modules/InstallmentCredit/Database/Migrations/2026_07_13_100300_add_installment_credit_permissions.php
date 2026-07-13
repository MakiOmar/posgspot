<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

class AddInstallmentCreditPermissions extends Migration
{
    public function up()
    {
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
    }

    public function down()
    {
        $permissions = [
            'installment.view',
            'installment.companies',
            'installment.settle',
            'installment.reports',
            'installment.import',
        ];

        Permission::whereIn('name', $permissions)->delete();
    }
}
