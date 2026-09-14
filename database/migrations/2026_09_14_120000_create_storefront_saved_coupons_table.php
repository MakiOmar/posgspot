<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_saved_coupons', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('contact_id');
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->string('code', 64);
            $table->timestamps();

            $table->unique(['contact_id', 'code'], 'storefront_saved_coupons_contact_code_unique');
            $table->index(['business_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_saved_coupons');
    }
};
