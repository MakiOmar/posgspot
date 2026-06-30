<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('variations', 'storefront_sale_price_inc_tax')) {
            return;
        }

        Schema::table('variations', function (Blueprint $table) {
            $table->decimal('storefront_sale_price_inc_tax', 22, 4)
                ->nullable()
                ->after('sell_price_inc_tax')
                ->comment('Optional online-only sale price (inc tax) for the public storefront');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('variations', 'storefront_sale_price_inc_tax')) {
            return;
        }

        Schema::table('variations', function (Blueprint $table) {
            $table->dropColumn('storefront_sale_price_inc_tax');
        });
    }
};
