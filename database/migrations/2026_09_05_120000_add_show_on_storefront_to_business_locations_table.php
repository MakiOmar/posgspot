<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_locations')) {
            return;
        }

        Schema::table('business_locations', function (Blueprint $table) {
            if (! Schema::hasColumn('business_locations', 'show_on_storefront')) {
                $table->boolean('show_on_storefront')->default(true)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('business_locations')) {
            return;
        }

        Schema::table('business_locations', function (Blueprint $table) {
            if (Schema::hasColumn('business_locations', 'show_on_storefront')) {
                $table->dropColumn('show_on_storefront');
            }
        });
    }
};
