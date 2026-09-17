<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_product_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('contact_id')->nullable();
            $table->string('name', 191);
            $table->string('email', 191);
            $table->string('phone', 50)->nullable();
            $table->string('product_name', 191);
            $table->string('platform', 20)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('new'); // new|contacted|fulfilled|closed
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'status']);
            $table->index('contact_id');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_product_requests');
    }
};
