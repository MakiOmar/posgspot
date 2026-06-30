<?php

namespace Tests\Unit;

use App\Category;
use Tests\TestCase;

/**
 * Category slug generation (storefront URLs: /category/{slug}).
 */
class CategorySlugTest extends TestCase
{
    protected int $businessId = 1;

    /** @var list<int> */
    private array $createdCategoryIds = [];

    protected function tearDown(): void
    {
        if ($this->createdCategoryIds !== []) {
            Category::withTrashed()
                ->whereIn('id', $this->createdCategoryIds)
                ->forceDelete();
        }

        parent::tearDown();
    }

    public function test_generate_slug_produces_url_safe_string(): void
    {
        $slug = Category::generateSlug('Accessories & Parts', $this->businessId, 'product');

        $this->assertSame('accessories-parts', $slug);
    }

    public function test_generate_slug_appends_numeric_suffix_on_collision(): void
    {
        $name = 'Slug Collision Test '.uniqid('', true);
        $baseSlug = Category::generateSlug($name, $this->businessId, 'product');

        $category = Category::create([
            'name' => $name,
            'slug' => $baseSlug,
            'business_id' => $this->businessId,
            'category_type' => 'product',
            'parent_id' => 0,
            'created_by' => 1,
        ]);
        $this->createdCategoryIds[] = $category->id;

        $nextSlug = Category::generateSlug($name, $this->businessId, 'product');

        $this->assertNotSame($baseSlug, $nextSlug);
        $this->assertMatchesRegularExpression('/^'.preg_quote($baseSlug, '/').'-\d+$/', $nextSlug);
    }

    public function test_generate_slug_ignores_current_record_on_edit(): void
    {
        $name = 'Slug Ignore Test '.uniqid('', true);
        $slug = Category::generateSlug($name, $this->businessId, 'product');

        $category = Category::create([
            'name' => $name,
            'slug' => $slug,
            'business_id' => $this->businessId,
            'category_type' => 'product',
            'parent_id' => 0,
            'created_by' => 1,
        ]);
        $this->createdCategoryIds[] = $category->id;

        $unchanged = Category::generateSlug($name, $this->businessId, 'product', $category->id);

        $this->assertSame($slug, $unchanged);
    }
}
