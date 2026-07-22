<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('storefront_media')) {
            return;
        }

        Schema::create('storefront_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            /** Relative path under public/uploads, e.g. storefront_library/1/abc.svg */
            $table->string('path', 500);
            $table->string('original_name', 255)->nullable();
            $table->string('mime', 120)->nullable();
            $table->string('kind', 20)->default('image'); // image|svg
            $table->unsignedInteger('bytes')->default(0);
            $table->string('checksum', 64)->index();
            $table->string('alt', 255)->nullable();
            $table->unsignedInteger('uploaded_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'checksum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_media');
    }
};
