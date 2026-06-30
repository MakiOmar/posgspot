<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'storefront_order_id')) {
                $table->string('storefront_order_id', 191)->nullable()->after('woocommerce_order_id');
                $table->unique(['business_id', 'storefront_order_id'], 'transactions_business_storefront_order_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'storefront_order_id')) {
                $table->dropUnique('transactions_business_storefront_order_unique');
                $table->dropColumn('storefront_order_id');
            }
        });
    }
};
