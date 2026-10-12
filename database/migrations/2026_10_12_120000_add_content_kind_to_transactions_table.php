<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * accounts_only | mixed | pos_only — lets staff filter sells / sales orders by Accounts content.
 * Filled by TransactionContentKindService; backfill with `php artisan sales:classify-content`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('transactions', 'content_kind')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('content_kind', 16)->nullable();
            $table->index(['business_id', 'type', 'content_kind'], 'transactions_business_type_content_kind_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('transactions', 'content_kind')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_business_type_content_kind_idx');
            $table->dropColumn('content_kind');
        });
    }
};
