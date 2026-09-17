<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_community_posts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->string('type', 20); // tournament|event|news
            $table->string('slug', 191);
            $table->string('status', 20)->default('draft'); // draft|published
            $table->string('cover_path', 500)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'slug'], 'sf_community_posts_biz_slug_unique');
            $table->index(['business_id', 'type', 'status'], 'sf_community_posts_biz_type_status');
            $table->index(['business_id', 'starts_at'], 'sf_community_posts_biz_starts');
        });

        Schema::create('storefront_community_post_translations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('community_post_id');
            $table->string('locale', 5); // en|ar
            $table->string('title', 191);
            $table->string('excerpt', 500)->nullable();
            $table->longText('body')->nullable();
            $table->timestamps();

            // MySQL identifier limit is 64 chars — default unique name is too long.
            $table->unique(['community_post_id', 'locale'], 'sf_community_post_tr_locale_unique');
            $table->index('locale');
            $table->foreign('community_post_id', 'sf_community_post_tr_post_fk')
                ->references('id')
                ->on('storefront_community_posts')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_community_post_translations');
        Schema::dropIfExists('storefront_community_posts');
    }
};
