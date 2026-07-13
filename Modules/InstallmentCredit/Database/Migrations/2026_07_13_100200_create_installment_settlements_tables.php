<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInstallmentSettlementsTables extends Migration
{
    public function up()
    {
        Schema::create('installment_settlements', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->unsignedInteger('company_id');
            $table->foreign('company_id')->references('id')->on('installment_companies')->onDelete('cascade');
            $table->unsignedInteger('location_id')->nullable();
            $table->date('settlement_date');
            $table->decimal('amount_booked', 22, 4)->default(0);
            $table->decimal('amount_received', 22, 4)->default(0);
            $table->decimal('fee_amount', 22, 4)->default(0);
            $table->unsignedInteger('account_id')->nullable();
            $table->unsignedInteger('account_transaction_id')->nullable();
            $table->unsignedInteger('fee_expense_transaction_id')->nullable();
            $table->string('external_ref')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'company_id']);
            $table->index(['business_id', 'settlement_date']);
        });

        Schema::create('installment_settlement_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('settlement_id');
            $table->foreign('settlement_id')->references('id')->on('installment_settlements')->onDelete('cascade');
            $table->unsignedInteger('receivable_id');
            $table->foreign('receivable_id')->references('id')->on('installment_receivables')->onDelete('cascade');
            $table->decimal('amount_booked', 22, 4)->default(0);
            $table->decimal('amount_received', 22, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('installment_settlement_lines');
        Schema::dropIfExists('installment_settlements');
    }
}
