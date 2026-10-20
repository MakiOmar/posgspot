<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shipping classes can mark products that never ship (checkout skips shipping rates and address checks).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('storefront_shipping_classes')
            || Schema::hasColumn('storefront_shipping_classes', 'requires_shipping')) {
            return;
        }

        Schema::table('storefront_shipping_classes', function (Blueprint $table) {
            $table->boolean('requires_shipping')->default(true)->after('slug');
        });

        // Classes operators already created as "No shipping" keep their intended meaning.
        DB::table('storefront_shipping_classes')
            ->whereIn('slug', ['no-shipping', 'no_shipping'])
            ->orWhere('name', 'No shipping')
            ->update(['requires_shipping' => false]);
    }

    public function down(): void
    {
        if (Schema::hasTable('storefront_shipping_classes')
            && Schema::hasColumn('storefront_shipping_classes', 'requires_shipping')) {
            Schema::table('storefront_shipping_classes', function (Blueprint $table) {
                $table->dropColumn('requires_shipping');
            });
        }
    }
};
