<?php

namespace App;

use App\Services\Storefront\CatalogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryTranslation extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        $forget = static function (CategoryTranslation $translation): void {
            $businessId = Category::withTrashed()->whereKey($translation->category_id)->value('business_id');
            if ($businessId) {
                CatalogService::forgetCategoriesCache((int) $businessId);
            }
        };

        static::saved($forget);
        static::deleted($forget);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
