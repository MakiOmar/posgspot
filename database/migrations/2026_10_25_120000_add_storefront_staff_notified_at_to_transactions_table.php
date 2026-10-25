<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When staff were alerted about a website order (once per order), plus the index behind the
 * "Website orders" queue (source = storefront, shipping_status = ordered).
 */
return new class extends Migration
{
    private const INDEX = 'transactions_business_source_shipping_index';

    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'storefront_staff_notified_at')) {
                $table->timestamp('storefront_staff_notified_at')->nullable();
            }
            if (! Schema::hasIndex('transactions', self::INDEX)) {
                $table->index(['business_id', 'source', 'shipping_status'], self::INDEX);
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasIndex('transactions', self::INDEX)) {
                $table->dropIndex(self::INDEX);
            }
            if (Schema::hasColumn('transactions', 'storefront_staff_notified_at')) {
                $table->dropColumn('storefront_staff_notified_at');
            }
        });
    }
};
