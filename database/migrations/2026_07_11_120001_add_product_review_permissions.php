<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [];
        foreach (['product_review.access', 'product_review.moderate'] as $name) {
            $permissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (Role::where('name', 'like', 'Admin#%')->get() as $role) {
            $role->givePermissionTo($permissions);
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', ['product_review.access', 'product_review.moderate'])->delete();
    }
};
