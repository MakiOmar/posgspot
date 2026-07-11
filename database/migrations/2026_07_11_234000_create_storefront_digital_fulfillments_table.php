<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_digital_fulfillments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('transaction_id');
            $table->string('storefront_order_id', 191);
            $table->unsignedInteger('sell_line_id')->nullable();
            $table->string('line_key', 191);
            $table->string('kind', 20); // game|card
            $table->unsignedBigInteger('accounts_order_id')->nullable();
            $table->string('status', 20)->default('pending'); // pending|allocated|failed
            $table->json('request_meta')->nullable();
            $table->text('secret_payload')->nullable(); // encrypted
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('allocated_at')->nullable();
            $table->timestamps();

            $table->index(['transaction_id', 'status']);
            $table->unique(['storefront_order_id', 'line_key'], 'sf_digital_order_line_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_digital_fulfillments');
    }
};
