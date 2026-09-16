<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('storefront_social_identities')) {
            return;
        }

        Schema::create('storefront_social_identities', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('contact_id');
            $table->string('provider', 32);
            $table->string('provider_user_id', 191);
            $table->string('email', 191)->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->timestamps();

            $table->unique(
                ['business_id', 'provider', 'provider_user_id'],
                'sf_social_biz_provider_uid_unique'
            );
            $table->unique(['contact_id', 'provider'], 'sf_social_contact_provider_unique');
            $table->index('contact_id');
            $table->index('business_id');

            $table->foreign('contact_id')
                ->references('id')
                ->on('contacts')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_social_identities');
    }
};
