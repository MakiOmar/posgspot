<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('brands') || Schema::hasColumn('brands', 'show_on_storefront')) {
            return;
        }

        Schema::table('brands', function (Blueprint $table) {
            // Existing brands stay visible; staff untick the ones to hide.
            $table->boolean('show_on_storefront')->default(true)->after('slug');
            $table->index(['business_id', 'show_on_storefront']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('brands') || ! Schema::hasColumn('brands', 'show_on_storefront')) {
            return;
        }

        Schema::table('brands', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'show_on_storefront']);
            $table->dropColumn('show_on_storefront');
        });
    }
};
