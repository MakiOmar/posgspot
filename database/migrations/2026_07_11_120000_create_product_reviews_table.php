<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('contact_id');
            $table->unsignedTinyInteger('rating');
            $table->string('title', 120)->nullable();
            $table->text('body');
            $table->string('status', 20)->default('pending'); // pending|approved|rejected
            $table->boolean('is_verified_purchase')->default(true);
            $table->unsignedInteger('moderated_by')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->string('moderator_note', 500)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'contact_id']);
            $table->index(['business_id', 'status']);
            $table->index(['product_id', 'status']);
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('storefront_rating_avg', 3, 2)->default(0)->after('slug');
            $table->unsignedInteger('storefront_rating_count')->default(0)->after('storefront_rating_avg');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['storefront_rating_avg', 'storefront_rating_count']);
        });

        Schema::dropIfExists('product_reviews');
    }
};
