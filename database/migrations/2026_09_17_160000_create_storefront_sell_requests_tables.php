<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_sell_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('contact_id');
            $table->string('type', 20); // account|disc|device
            $table->string('name', 191);
            $table->string('phone', 50)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('city', 120)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('purchased_from_us')->default(false);
            $table->string('invoice_no', 191)->nullable();
            $table->unsignedInteger('transaction_id')->nullable();
            $table->json('details')->nullable();
            $table->string('status', 20)->default('new'); // new|contacted|accepted|rejected|closed
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'type']);
            $table->index('contact_id');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('set null');
        });

        Schema::create('storefront_sell_request_media', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('sell_request_id');
            $table->string('path', 500);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('sell_request_id');
            $table->foreign('sell_request_id')
                ->references('id')
                ->on('storefront_sell_requests')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_sell_request_media');
        Schema::dropIfExists('storefront_sell_requests');
    }
};
