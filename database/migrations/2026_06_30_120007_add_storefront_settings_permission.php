<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'storefront.settings', 'guard_name' => 'web']
        );

        $businessId = (int) config('storefront.business_id', 1);
        $role = Role::where('name', 'Admin#'.$businessId)->first();

        if ($role && ! $role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        Permission::where('name', 'storefront.settings')->delete();
    }
};
