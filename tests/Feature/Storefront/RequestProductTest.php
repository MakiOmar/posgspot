<?php

namespace Tests\Feature\Storefront;

use App\Mail\StorefrontProductRequestSubmitted;
use App\StorefrontProductRequest;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RequestProductTest extends TestCase
{
    protected int $businessId = 1;

    protected function setUp(): void
    {
        parent::setUp();
        config(['storefront.request_product.enabled' => true]);
        Mail::fake();
    }

    public function test_request_product_unavailable_when_disabled(): void
    {
        config(['storefront.request_product.enabled' => false]);

        $this->getJson('/api/storefront/v1/request-product/meta')
            ->assertStatus(404);

        $this->postJson('/api/storefront/v1/request-product/requests', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'product_name' => 'Game X',
        ])->assertStatus(404);
    }

    public function test_settings_exposes_request_product_flag(): void
    {
        $this->getJson('/api/storefront/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.request_product.enabled', true);
    }

    public function test_meta_returns_platforms(): void
    {
        $this->getJson('/api/storefront/v1/request-product/meta')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonStructure(['data' => ['platforms']]);
    }

    public function test_guest_can_submit_request(): void
    {
        $payload = [
            'name' => 'Guest User',
            'email' => 'guest_'.uniqid().'@example.com',
            'phone' => '+201012345678',
            'product_name' => 'Missing Game',
            'platform' => 'ps5',
            'notes' => 'Any edition',
        ];

        $this->postJson('/api/storefront/v1/request-product/requests', $payload)
            ->assertCreated()
            ->assertJsonPath('data.product_name', 'Missing Game')
            ->assertJsonPath('data.status', StorefrontProductRequest::STATUS_NEW);

        $this->assertDatabaseHas('storefront_product_requests', [
            'business_id' => $this->businessId,
            'email' => $payload['email'],
            'product_name' => 'Missing Game',
        ]);

        Mail::assertQueued(StorefrontProductRequestSubmitted::class);
    }
}
