<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const NEW_PERMISSIONS = ['storefront.community', 'storefront.requests'];

    public function up(): void
    {
        $permissions = [];
        foreach (self::NEW_PERMISSIONS as $name) {
            $permissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $settings = Permission::where('name', 'storefront.settings')->where('guard_name', 'web')->first();

        // These pages used to sit behind storefront.settings; keep access for roles that already had it.
        $roles = Role::where('name', 'like', 'Admin#%')
            ->when($settings, fn ($q) => $q->orWhereHas('permissions', fn ($p) => $p->whereKey($settings->id)))
            ->get();

        foreach ($roles as $role) {
            $role->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', self::NEW_PERMISSIONS)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
