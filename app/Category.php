<?php

namespace App;

use DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Category extends Model
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
     * Combines Category and sub-category
     *
     * @param  int  $business_id
     * @return array
     */
    public static function catAndSubCategories($business_id)
    {
        $all_categories = Category::where('business_id', $business_id)
                                ->where('category_type', 'product')
                                ->orderBy('name', 'asc')
                                ->get()
                                ->toArray();

        if (empty($all_categories)) {
            return [];
        }
        $categories = [];
        $sub_categories = [];

        foreach ($all_categories as $category) {
            if ($category['parent_id'] == 0) {
                $categories[] = $category;
            } else {
                $sub_categories[] = $category;
            }
        }

        $sub_cat_by_parent = [];
        if (! empty($sub_categories)) {
            foreach ($sub_categories as $sub_category) {
                if (empty($sub_cat_by_parent[$sub_category['parent_id']])) {
                    $sub_cat_by_parent[$sub_category['parent_id']] = [];
                }

                $sub_cat_by_parent[$sub_category['parent_id']][] = $sub_category;
            }
        }

        foreach ($categories as $key => $value) {
            if (! empty($sub_cat_by_parent[$value['id']])) {
                $categories[$key]['sub_categories'] = $sub_cat_by_parent[$value['id']];
            }
        }

        return $categories;
    }

    /**
     * Category Dropdown
     *
     * @param  int  $business_id
     * @param  string  $type category type
     * @return array
     */
    public static function forDropdown($business_id, $type)
    {
        $categories = Category::where('business_id', $business_id)
                            ->where('parent_id', 0)
                            ->where('category_type', $type)
                            ->select(DB::raw('IF(short_code IS NOT NULL, CONCAT(name, "-", short_code), name) as name'), 'id')
                            ->orderBy('name', 'asc')
                            ->get();

        $dropdown = $categories->pluck('name', 'id');

        return $dropdown;
    }

    public function sub_categories()
    {
        return $this->hasMany(\App\Category::class, 'parent_id');
    }

    /**
     * Generate a URL-safe, unique slug for a category name.
     *
     * Uniqueness is scoped to the business and category type so storefront
     * URLs (e.g. /category/accessories) resolve to a single product category.
     * Clashes get WordPress-style numeric suffixes: base, base-2, base-3, …
     *
     * @param  string  $name  Source name to slugify.
     * @param  int  $businessId  Owning business.
     * @param  string  $categoryType  e.g. 'product'.
     * @param  int|null  $excludeId  Current record id to ignore on edit.
     */
    public static function generateSlug(string $name, int $businessId, string $categoryType = 'product', ?int $excludeId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $i = 1;

        while (static::where('business_id', $businessId)
            ->where('category_type', $categoryType)
            ->where('slug', $slug)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    /**
     * Scope a query to only include main categories.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOnlyParent($query)
    {
        return $query->where('parent_id', 0);
    }
}
