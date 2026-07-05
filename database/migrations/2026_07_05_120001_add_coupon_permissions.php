<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['coupon.access', 'coupon.create', 'coupon.delete'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', ['coupon.access', 'coupon.create', 'coupon.delete'])->delete();
    }
};
