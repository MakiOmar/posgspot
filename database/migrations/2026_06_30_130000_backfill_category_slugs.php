<?php

use App\Category;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Backfill unique slugs for product categories that don't have one yet.
     * Slugs power storefront category URLs (e.g. /category/accessories).
     */
    public function up(): void
    {
        Category::where('category_type', 'product')
            ->where(function ($q) {
                $q->whereNull('slug')->orWhere('slug', '');
            })
            ->orderBy('id')
            ->chunkById(200, function ($categories) {
                foreach ($categories as $category) {
                    $category->slug = Category::generateSlug(
                        (string) $category->name,
                        (int) $category->business_id,
                        $category->category_type ?? 'product',
                        $category->id
                    );
                    $category->save();
                }
            });
    }

    /**
     * Non-destructive: leave generated slugs in place on rollback.
     */
    public function down(): void
    {
    }
};
