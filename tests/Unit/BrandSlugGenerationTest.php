<?php

namespace Tests\Unit;

use App\Brands;
use Tests\TestCase;

class BrandSlugGenerationTest extends TestCase
{
    protected int $businessId = 1;

    public function test_generate_slug_from_name(): void
    {
        $slug = Brands::generateSlug('Sony PlayStation', $this->businessId);

        $this->assertSame('sony-playstation', $slug);
    }

    public function test_generate_slug_avoids_existing_slug(): void
    {
        $existing = Brands::where('business_id', $this->businessId)
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->first();

        if (empty($existing)) {
            $this->markTestSkipped('No brand with slug in database.');
        }

        $base = \Illuminate\Support\Str::slug((string) $existing->name) ?: 'brand';
        $slug = Brands::generateSlug($existing->name, $this->businessId);

        $this->assertNotSame($existing->slug, $slug);
        $this->assertTrue(str_starts_with($slug, $base));
    }

    public function test_generate_slug_can_keep_own_slug_when_excluded(): void
    {
        $existing = Brands::where('business_id', $this->businessId)
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->first();

        if (empty($existing)) {
            $this->markTestSkipped('No brand with slug in database.');
        }

        $slug = Brands::generateSlug($existing->name, $this->businessId, $existing->id);

        $this->assertSame($existing->slug, $slug);
    }
}
