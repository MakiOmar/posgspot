<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'storefront_payment_meta')) {
                $table->json('storefront_payment_meta')->nullable()->after('storefront_order_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'storefront_payment_meta')) {
                $table->dropColumn('storefront_payment_meta');
            }
        });
    }
};
