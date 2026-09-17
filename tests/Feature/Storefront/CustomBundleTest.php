<?php

namespace Tests\Feature\Storefront;

use Tests\TestCase;

class CustomBundleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'storefront.custom_bundle.enabled' => true,
            'storefront.custom_bundle.min_items' => 2,
            'storefront.custom_bundle.max_items' => 15,
        ]);
    }

    public function test_custom_bundle_unavailable_when_disabled(): void
    {
        config(['storefront.custom_bundle.enabled' => false]);

        $this->getJson('/api/storefront/v1/custom-bundle/meta')
            ->assertStatus(404);

        $this->getJson('/api/storefront/v1/custom-bundle/products?platform=ps5')
            ->assertStatus(404);
    }

    public function test_settings_exposes_custom_bundle_flag(): void
    {
        $this->getJson('/api/storefront/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.custom_bundle.enabled', true)
            ->assertJsonPath('data.custom_bundle.min_items', 2)
            ->assertJsonPath('data.custom_bundle.max_items', 15);

        config(['storefront.custom_bundle.enabled' => false]);

        $this->getJson('/api/storefront/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.custom_bundle.enabled', false);
    }

    public function test_meta_returns_platforms_tabs_and_limits(): void
    {
        $this->getJson('/api/storefront/v1/custom-bundle/meta')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.min_items', 2)
            ->assertJsonPath('data.max_items', 15)
            ->assertJsonPath('data.platforms.0.id', 'ps5')
            ->assertJsonPath('data.platforms.1.id', 'ps4')
            ->assertJsonPath('data.tabs.0.id', 'all');
    }

    public function test_products_require_platform(): void
    {
        $this->getJson('/api/storefront/v1/custom-bundle/products')
            ->assertStatus(422);
    }

    public function test_products_accept_valid_platform(): void
    {
        $this->getJson('/api/storefront/v1/custom-bundle/products?platform=ps5&tab=all')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total', 'min_items', 'max_items'],
            ]);
    }
}
