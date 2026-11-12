<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'storefront.orders';

    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => 'web']);

        // Website orders used to follow sell.view / direct_sell.view; keep access for roles that already had it.
        $salesViewIds = Permission::whereIn('name', ['sell.view', 'direct_sell.view'])
            ->where('guard_name', 'web')
            ->pluck('id');

        $roles = Role::where('name', 'like', 'Admin#%')
            ->orWhereHas('permissions', fn ($q) => $q->whereKey($salesViewIds))
            ->get();

        foreach ($roles as $role) {
            $role->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISSION)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
