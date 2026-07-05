<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('contact_id');
            $table->unsignedInteger('product_id');
            $table->timestamps();

            $table->unique(['contact_id', 'product_id'], 'storefront_wishlist_contact_product_unique');
            $table->index(['business_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_wishlist_items');
    }
};
