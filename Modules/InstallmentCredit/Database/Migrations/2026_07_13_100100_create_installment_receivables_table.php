<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInstallmentReceivablesTable extends Migration
{
    public function up()
    {
        Schema::create('installment_receivables', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->unsignedInteger('location_id')->nullable();
            $table->unsignedInteger('company_id');
            $table->foreign('company_id')->references('id')->on('installment_companies')->onDelete('cascade');
            $table->unsignedInteger('transaction_id')->nullable();
            $table->unsignedInteger('transaction_payment_id')->nullable();
            $table->string('invoice_no')->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('due_amount', 22, 4)->default(0);
            $table->decimal('booked_settled_amount', 22, 4)->default(0);
            $table->decimal('actual_received_amount', 22, 4)->default(0);
            $table->enum('status', ['pending', 'settled', 'cancelled'])->default('pending');
            $table->dateTime('settled_on')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_imported')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'transaction_id', 'company_id'], 'ic_recv_biz_txn_company_unique');
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'due_date']);
            $table->index('transaction_payment_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('installment_receivables');
    }
}
