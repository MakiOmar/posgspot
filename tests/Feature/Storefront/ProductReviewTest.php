<?php

namespace Tests\Feature\Storefront;

use App\BusinessLocation;
use App\Product;
use App\ProductReview;
use App\Services\Storefront\ProductReviewService;
use App\Services\Storefront\StorefrontSettingService;
use App\Transaction;
use App\TransactionSellLine;
use App\Variation;
use Tests\TestCase;

/**
 * Storefront product reviews API (submit, list, moderation recompute).
 */
class ProductReviewTest extends TestCase
{
    protected int $businessId = 1;

    private function registerAndLogin(): array
    {
        $email = 'review_test_'.uniqid().'@example.com';
        $mobile = '+2010'.random_int(10000000, 99999999);

        $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Review',
            'last_name' => 'Tester',
            'email' => $email,
            'mobile' => $mobile,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $login = $this->postJson('/api/storefront/v1/auth/login', [
            'login' => $email,
            'password' => 'password123',
        ])->assertOk();

        return [
            'token' => $login->json('data.token'),
            'contact_id' => (int) $login->json('data.contact.id'),
        ];
    }

    private function sellableProduct(): ?Product
    {
        $location = BusinessLocation::where('business_id', $this->businessId)
            ->where('is_active', 1)
            ->first();

        if (empty($location)) {
            return null;
        }

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$location->id],
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        return Product::where('business_id', $this->businessId)
            ->where('is_inactive', 0)
            ->where('not_for_selling', 0)
            ->whereHas('product_locations', fn ($q) => $q->where('product_locations.location_id', $location->id))
            ->first();
    }

    private function createFinalPurchase(int $contactId, Product $product): void
    {
        $variation = Variation::where('product_id', $product->id)->whereNull('deleted_at')->first();
        $this->assertNotNull($variation, 'Product needs a variation for sell line.');

        $transaction = Transaction::create([
            'business_id' => $this->businessId,
            'location_id' => BusinessLocation::where('business_id', $this->businessId)->where('is_active', 1)->value('id'),
            'type' => 'sell',
            'status' => 'final',
            'contact_id' => $contactId,
            'transaction_date' => now(),
            'total_before_tax' => 100,
            'final_total' => 100,
            'created_by' => 1,
        ]);

        TransactionSellLine::create([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'variation_id' => $variation->id,
            'quantity' => 1,
            'unit_price' => 100,
            'unit_price_inc_tax' => 100,
            'item_tax' => 0,
            'line_discount_amount' => 0,
        ]);
    }

    public function test_reviews_list_requires_no_auth_and_hides_pending(): void
    {
        $product = $this->sellableProduct();
        if (empty($product)) {
            $this->markTestSkipped('No sellable product in database.');
        }

        $session = $this->registerAndLogin();
        $this->createFinalPurchase($session['contact_id'], $product);

        $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/products/'.$product->id.'/reviews', [
                'rating' => 5,
                'body' => 'Great product, works as expected.',
            ])
            ->assertCreated();

        $this->getJson('/api/storefront/v1/products/'.$product->id.'/reviews')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 0);
    }

    public function test_submit_requires_auth_and_purchase(): void
    {
        $product = $this->sellableProduct();
        if (empty($product)) {
            $this->markTestSkipped('No sellable product in database.');
        }

        $this->postJson('/api/storefront/v1/products/'.$product->id.'/reviews', [
            'rating' => 5,
            'body' => 'Great product, works as expected.',
        ])->assertUnauthorized();

        $session = $this->registerAndLogin();

        $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/products/'.$product->id.'/reviews', [
                'rating' => 5,
                'body' => 'Great product, works as expected.',
            ])
            ->assertStatus(422);

        $this->createFinalPurchase($session['contact_id'], $product);

        $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/products/'.$product->id.'/reviews', [
                'rating' => 4,
                'title' => 'Solid',
                'body' => 'Bought in store and happy with it.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('product_reviews', [
            'product_id' => $product->id,
            'contact_id' => $session['contact_id'],
            'status' => ProductReview::STATUS_PENDING,
            'rating' => 4,
        ]);
    }

    public function test_approve_exposes_review_and_updates_product_rating(): void
    {
        $product = $this->sellableProduct();
        if (empty($product)) {
            $this->markTestSkipped('No sellable product in database.');
        }

        $session = $this->registerAndLogin();
        $this->createFinalPurchase($session['contact_id'], $product);

        $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/products/'.$product->id.'/reviews', [
                'rating' => 5,
                'body' => 'Excellent quality and fast delivery.',
            ])
            ->assertCreated();

        $review = ProductReview::where('product_id', $product->id)
            ->where('contact_id', $session['contact_id'])
            ->firstOrFail();

        app(ProductReviewService::class)->approve($review, 1);

        $this->getJson('/api/storefront/v1/products/'.$product->id.'/reviews')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.rating', 5)
            ->assertJsonPath('data.0.is_verified_purchase', true);

        $detail = $this->getJson('/api/storefront/v1/products/'.$product->id)->assertOk();
        $detail->assertJsonPath('data.rating.average', 5);
        $detail->assertJsonPath('data.rating.count', 1);

        $product->refresh();
        $this->assertSame(1, (int) $product->storefront_rating_count);
        $this->assertEquals(5.0, (float) $product->storefront_rating_avg);
    }

    public function test_eligibility_endpoint(): void
    {
        $product = $this->sellableProduct();
        if (empty($product)) {
            $this->markTestSkipped('No sellable product in database.');
        }

        $session = $this->registerAndLogin();

        $this->withToken($session['token'])
            ->getJson('/api/storefront/v1/products/'.$product->id.'/reviews/eligibility')
            ->assertOk()
            ->assertJsonPath('data.can_review', false)
            ->assertJsonPath('data.reason', 'not_purchased');

        $this->createFinalPurchase($session['contact_id'], $product);

        $this->withToken($session['token'])
            ->getJson('/api/storefront/v1/products/'.$product->id.'/reviews/eligibility')
            ->assertOk()
            ->assertJsonPath('data.can_review', true);
    }
}
