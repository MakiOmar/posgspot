<?php

use App\Brands;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            if (! Schema::hasColumn('brands', 'slug')) {
                $table->string('slug')->nullable()->index()->after('name');
            }
        });

        Brands::query()->whereNull('slug')->orWhere('slug', '')->orderBy('id')->chunkById(100, function ($brands) {
            foreach ($brands as $brand) {
                $base = Str::slug((string) $brand->name) ?: 'brand-'.$brand->id;
                $slug = $base;
                $i = 1;
                while (
                    Brands::where('business_id', $brand->business_id)
                        ->where('slug', $slug)
                        ->where('id', '!=', $brand->id)
                        ->exists()
                ) {
                    $slug = $base.'-'.$i;
                    $i++;
                }
                $brand->slug = $slug;
                $brand->save();
            }
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            if (Schema::hasColumn('brands', 'slug')) {
                $table->dropColumn('slug');
            }
        });
    }
};
