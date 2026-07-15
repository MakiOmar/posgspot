<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Homepage shelf presentation lives on the product category (single source of truth).
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'show_on_homepage_shelf')) {
                $table->boolean('show_on_homepage_shelf')->default(0)->after('image');
                $table->unsignedInteger('homepage_shelf_sort')->default(0)->after('show_on_homepage_shelf');
                $table->string('shelf_banner')->nullable()->after('homepage_shelf_sort');
                $table->string('shelf_heading')->nullable()->after('shelf_banner');
                $table->string('shelf_banner_kicker')->nullable()->after('shelf_heading');
                $table->string('shelf_banner_text')->nullable()->after('shelf_banner_kicker');
                $table->string('shelf_button_text')->nullable()->after('shelf_banner_text');
                $table->string('shelf_banner_link', 500)->nullable()->after('shelf_button_text');
                $table->string('shelf_view_more_label')->nullable()->after('shelf_banner_link');
                $table->index(['category_type', 'show_on_homepage_shelf', 'homepage_shelf_sort'], 'categories_homepage_shelf_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'show_on_homepage_shelf')) {
                $table->dropIndex('categories_homepage_shelf_idx');
                $table->dropColumn([
                    'show_on_homepage_shelf',
                    'homepage_shelf_sort',
                    'shelf_banner',
                    'shelf_heading',
                    'shelf_banner_kicker',
                    'shelf_banner_text',
                    'shelf_button_text',
                    'shelf_banner_link',
                    'shelf_view_more_label',
                ]);
            }
        });
    }
};
