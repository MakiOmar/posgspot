<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Links destination purchase_lines (purchase_transfer) to the paired sell line on the stock transfer.
     */
    public function up(): void
    {
        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->unsignedInteger('transaction_sell_line_id')->nullable()->after('transaction_id');
            $table->index('transaction_sell_line_id');
            $table->foreign('transaction_sell_line_id')
                ->references('id')
                ->on('transaction_sell_lines')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->dropForeign(['transaction_sell_line_id']);
        });
    }
};
