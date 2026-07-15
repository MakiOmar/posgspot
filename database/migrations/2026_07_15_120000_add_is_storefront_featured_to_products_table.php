<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Featured on public storefront homepage (deals / featured rail).
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'is_storefront_featured')) {
                $table->boolean('is_storefront_featured')->default(0)->after('not_for_selling');
                $table->index('is_storefront_featured');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'is_storefront_featured')) {
                $table->dropIndex(['is_storefront_featured']);
                $table->dropColumn('is_storefront_featured');
            }
        });
    }
};
