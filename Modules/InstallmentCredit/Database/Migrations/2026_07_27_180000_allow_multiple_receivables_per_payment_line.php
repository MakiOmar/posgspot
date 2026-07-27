<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow multiple pending receivables on the same invoice for the same company
 * (one row per BNPL payment line), keyed by transaction_payment_id.
 */
class AllowMultipleReceivablesPerPaymentLine extends Migration
{
    public function up()
    {
        Schema::table('installment_receivables', function (Blueprint $table) {
            // Drop company-scoped unique (one row per company per invoice).
            try {
                $table->dropUnique('ic_recv_biz_txn_company_unique');
            } catch (\Throwable $e) {
                // Index may already be gone on some environments.
            }
        });

        Schema::table('installment_receivables', function (Blueprint $table) {
            // One receivable per payment line (manual rows keep null payment_id).
            try {
                $table->unique('transaction_payment_id', 'ic_recv_payment_unique');
            } catch (\Throwable $e) {
                // Already present.
            }
        });
    }

    public function down()
    {
        Schema::table('installment_receivables', function (Blueprint $table) {
            try {
                $table->dropUnique('ic_recv_payment_unique');
            } catch (\Throwable $e) {
                //
            }
        });

        Schema::table('installment_receivables', function (Blueprint $table) {
            try {
                $table->unique(['business_id', 'transaction_id', 'company_id'], 'ic_recv_biz_txn_company_unique');
            } catch (\Throwable $e) {
                //
            }
        });
    }
}
