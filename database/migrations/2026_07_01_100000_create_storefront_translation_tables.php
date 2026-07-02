<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('product_id');
            $table->string('locale', 10);
            $table->string('name');
            $table->text('product_description')->nullable();
            $table->string('slug')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'locale']);
            $table->index(['locale', 'slug']);
        });

        Schema::create('category_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('category_id');
            $table->string('locale', 10);
            $table->string('name');
            $table->string('slug')->nullable();
            $table->timestamps();

            $table->unique(['category_id', 'locale']);
            $table->index(['locale', 'slug']);
        });

        Schema::create('brand_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('brand_id');
            $table->string('locale', 10);
            $table->string('name');
            $table->timestamps();

            $table->unique(['brand_id', 'locale']);
        });

        Schema::create('variation_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('variation_id');
            $table->string('locale', 10);
            $table->string('name');
            $table->timestamps();

            $table->unique(['variation_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variation_translations');
        Schema::dropIfExists('brand_translations');
        Schema::dropIfExists('category_translations');
        Schema::dropIfExists('product_translations');
    }
};
