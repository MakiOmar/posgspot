<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_community_posts', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('status');
            $table->unsignedInteger('location_id')->nullable()->after('is_featured');
            $table->string('prize_pool', 191)->nullable()->after('location_id');
            $table->string('entry_fee', 191)->nullable()->after('prize_pool');
            $table->unsignedInteger('available_spots')->nullable()->after('entry_fee');
            $table->string('registration_mode', 20)->default('off')->after('available_spots');
            $table->string('registration_url', 500)->nullable()->after('registration_mode');
            $table->boolean('registration_open')->default(false)->after('registration_url');
            $table->string('winner', 191)->nullable()->after('registration_open');

            $table->index(['business_id', 'type', 'is_featured'], 'sf_community_posts_biz_type_featured');
            $table->index('location_id', 'sf_community_posts_location');
        });

        Schema::table('storefront_community_post_translations', function (Blueprint $table) {
            $table->string('game_title', 191)->nullable()->after('title');
            $table->longText('rules')->nullable()->after('body');
            $table->longText('results')->nullable()->after('rules');
            $table->longText('highlights')->nullable()->after('results');
            $table->longText('recap')->nullable()->after('highlights');
            $table->text('registration_details')->nullable()->after('recap');
        });

        Schema::create('storefront_community_post_media', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('community_post_id');
            $table->string('kind', 20)->default('image'); // image|video
            $table->string('path', 500);
            $table->string('caption', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('community_post_id', 'sf_community_media_post');
            $table->foreign('community_post_id', 'sf_community_media_post_fk')
                ->references('id')
                ->on('storefront_community_posts')
                ->onDelete('cascade');
        });

        Schema::create('storefront_community_applications', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('community_post_id');
            $table->string('name', 191);
            $table->string('mobile', 50);
            $table->string('locale', 5)->default('en');
            $table->string('source', 20)->default('web'); // web|mobile
            $table->unsignedInteger('contact_id')->nullable();
            $table->string('status', 20)->default('new'); // new|reviewed|accepted|rejected
            $table->timestamps();

            $table->index(['business_id', 'status'], 'sf_community_apps_biz_status');
            $table->index(['community_post_id', 'mobile'], 'sf_community_apps_post_mobile');
            $table->foreign('community_post_id', 'sf_community_apps_post_fk')
                ->references('id')
                ->on('storefront_community_posts')
                ->onDelete('cascade');
            $table->foreign('contact_id', 'sf_community_apps_contact_fk')
                ->references('id')
                ->on('contacts')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_community_applications');
        Schema::dropIfExists('storefront_community_post_media');

        Schema::table('storefront_community_post_translations', function (Blueprint $table) {
            $table->dropColumn([
                'game_title',
                'rules',
                'results',
                'highlights',
                'recap',
                'registration_details',
            ]);
        });

        Schema::table('storefront_community_posts', function (Blueprint $table) {
            $table->dropIndex('sf_community_posts_biz_type_featured');
            $table->dropIndex('sf_community_posts_location');
            $table->dropColumn([
                'is_featured',
                'location_id',
                'prize_pool',
                'entry_fee',
                'available_spots',
                'registration_mode',
                'registration_url',
                'registration_open',
                'winner',
            ]);
        });
    }
};
