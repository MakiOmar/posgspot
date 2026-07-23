<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_device_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('contact_id');
            $table->string('platform', 16); // ios | android
            $table->string('token', 512);
            $table->string('locale', 8)->nullable();
            $table->timestamps();

            $table->unique(['contact_id', 'token'], 'storefront_device_contact_token_unique');
            $table->index(['business_id', 'contact_id']);
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_device_tokens');
    }
};
