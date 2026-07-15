<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foreground product image on homepage shelf banner (between title and CTA).
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'shelf_fg_image')) {
                $table->string('shelf_fg_image')->nullable()->after('shelf_banner');
            }
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'shelf_fg_image')) {
                $table->dropColumn('shelf_fg_image');
            }
        });
    }
};
