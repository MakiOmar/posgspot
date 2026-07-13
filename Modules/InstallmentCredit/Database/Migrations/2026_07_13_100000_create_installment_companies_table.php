<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInstallmentCompaniesTable extends Migration
{
    public function up()
    {
        Schema::create('installment_companies', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->string('name');
            $table->string('code', 64);
            $table->boolean('is_active')->default(1);
            $table->unsignedSmallInteger('default_settlement_days')->default(20);
            $table->enum('fee_mode', ['none', 'percent', 'fixed'])->default('none');
            $table->decimal('fee_percent', 8, 4)->nullable();
            $table->decimal('fee_fixed', 22, 4)->nullable();
            $table->string('payment_method_key', 32)->nullable()->comment('custom_pay_N mapped in POS');
            $table->unsignedInteger('default_deposit_account_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'code']);
            $table->index(['business_id', 'payment_method_key']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('installment_companies');
    }
}
