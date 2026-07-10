<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Brands extends Model
{
    use SoftDeletes;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * URL-safe unique slug for storefront brand pages (scoped per business).
     *
     * Clashes get numeric suffixes: base, base-1, base-2, …
     */
    public static function generateSlug(string $name, int $businessId, ?int $excludeId = null): string
    {
        $base = Str::slug($name) ?: 'brand';
        $slug = $base;
        $i = 1;

        while (static::where('business_id', $businessId)
            ->where('slug', $slug)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    /**
     * Return list of brands for a business
     *
     * @param  int  $business_id
     * @param  bool  $show_none = false
     * @return array
     */
    public static function forDropdown($business_id, $show_none = false, $filter_use_for_repair = false)
    {
        $query = Brands::where('business_id', $business_id);

        if ($filter_use_for_repair) {
            $query->where('use_for_repair', 1);
        }

        $brands = $query->orderBy('name', 'asc')
                    ->pluck('name', 'id');

        if ($show_none) {
            $brands->prepend(__('lang_v1.none'), '');
        }

        return $brands;
    }

    public function storefrontTranslations()
    {
        return $this->hasMany(BrandTranslation::class, 'brand_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'brand_id');
    }
}
