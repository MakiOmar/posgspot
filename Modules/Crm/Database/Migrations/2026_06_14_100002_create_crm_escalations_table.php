<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCrmEscalationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('crm_escalations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('business_id')->unsigned();
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->string('reference_no');
            $table->integer('employee_id')->unsigned();
            $table->foreign('employee_id')->references('id')->on('users')->onDelete('cascade');
            $table->integer('contact_id')->unsigned();
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->string('phone', 20)->nullable();
            $table->dateTime('escalated_at');
            $table->text('description');
            $table->integer('source_id')->unsigned();
            $table->foreign('source_id')->references('id')->on('crm_escalation_sources')->onDelete('cascade');
            $table->integer('location_id')->unsigned();
            $table->foreign('location_id')->references('id')->on('business_locations')->onDelete('cascade');
            $table->dateTime('callback_at')->nullable();
            $table->integer('transaction_id')->unsigned()->nullable();
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('set null');
            $table->text('comment')->nullable();
            $table->integer('observer_id')->unsigned()->nullable();
            $table->foreign('observer_id')->references('id')->on('users')->onDelete('set null');
            $table->text('observer_comment')->nullable();
            $table->integer('auditor_id')->unsigned()->nullable();
            $table->foreign('auditor_id')->references('id')->on('users')->onDelete('set null');
            $table->enum('status', ['open', 'in_review', 'callback_scheduled', 'resolved', 'closed', 'cancelled'])->default('open');
            $table->integer('created_by')->unsigned();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->integer('updated_by')->unsigned()->nullable();
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index('business_id');
            $table->index('contact_id');
            $table->index('status');
            $table->index('escalated_at');
            $table->index('callback_at');
            $table->index('employee_id');
            $table->index('observer_id');
            $table->index('auditor_id');
            $table->index('transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('crm_escalations');
    }
}
