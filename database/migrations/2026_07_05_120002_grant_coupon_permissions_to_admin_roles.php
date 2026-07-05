<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Ensures every business Admin role can manage promo codes (POS has no role editor).
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissionNames = ['coupon.access', 'coupon.create', 'coupon.delete'];
        $permissions = [];

        foreach ($permissionNames as $name) {
            $permissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (Role::where('name', 'like', 'Admin#%')->get() as $role) {
            $role->givePermissionTo($permissions);
        }
    }

    public function down(): void
    {
        // Permissions remain; only role links would be removed manually if needed.
    }
};
