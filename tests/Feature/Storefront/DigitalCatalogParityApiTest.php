<?php

namespace Tests\Feature\Storefront;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Digital catalog Accounts proxy: product_type, gallery/reviews, full offers, review submit.
 */
class DigitalCatalogParityApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.accounts.base' => 'https://accounts.test',
            'services.accounts.phone' => '01000000000',
            'services.accounts.password' => 'secret',
        ]);
        Cache::flush();
    }

    public function test_list_forwards_product_type_subscription(): void
    {
        Http::fake([
            'accounts.test/api/games/platform/*' => Http::response([
                'data' => [
                    [
                        'id' => 9,
                        'title' => 'PS Plus Essential',
                        'product_type' => 'subscription',
                        'types' => [
                            'primary' => ['available' => true, 'stock' => 3, 'price' => 400],
                            'secondary' => ['available' => false, 'stock' => 0, 'price' => 0],
                            'full' => ['available' => true, 'stock' => 1, 'price' => 900],
                        ],
                    ],
                ],
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 20,
                'total' => 1,
            ], 200),
        ]);

        $this->getJson('/api/storefront/v1/digital/games?platform=5&product_type=subscription')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product_type', 'subscription')
            ->assertJsonPath('data.games.0.product_type', 'subscription')
            ->assertJsonPath('data.games.0.full_status', true)
            ->assertJsonPath('data.games.0.total_full_stock', 1)
            ->assertJsonPath('data.games.0.full_price', 900);

        Http::assertSent(function ($request) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_contains($request->url(), '/api/games/platform/5')
                && ($query['product_type'] ?? null) === 'subscription';
        });
    }

    public function test_game_detail_includes_gallery_reviews_and_full_stock(): void
    {
        Http::fake([
            'accounts.test/api/games/42' => Http::response([
                'data' => [
                    'id' => 42,
                    'title' => 'Horizon',
                    'product_type' => 'game',
                    'description' => '<p>Open <script>alert(1)</script>world</p>',
                    'ps5_full_stock' => '2',
                    'ps5_full_price' => 1200,
                    'ps5_full_status' => 1,
                    'ps5_primary_stock' => 0,
                    'gallery' => [
                        ['id' => 1, 'url' => '/uploads/a.jpg', 'sort_order' => 2],
                        ['id' => 2, 'path' => '/uploads/b.jpg', 'sort_order' => 1],
                    ],
                    'reviews' => [
                        'average' => 4.5,
                        'count' => 2,
                        'items' => [
                            [
                                'id' => 7,
                                'stars' => 5,
                                'comment' => '<b>Great</b>',
                                'reviewer_name' => 'Alex',
                                'created_at' => '2026-09-01T00:00:00Z',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/storefront/v1/digital/games/42')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.game.id', 42)
            ->assertJsonPath('data.game.ps5_full_stock', 2)
            ->assertJsonPath('data.game.reviews.average', 4.5)
            ->assertJsonPath('data.game.reviews.count', 2)
            ->assertJsonPath('data.game.reviews.items.0.stars', 5)
            ->assertJsonPath('data.game.reviews.items.0.comment', 'Great');

        $gallery = $response->json('data.game.gallery');
        $this->assertIsArray($gallery);
        $this->assertCount(2, $gallery);
        $this->assertSame(1, $gallery[0]['sort_order']);
        $this->assertStringContainsString('uploads/b.jpg', $gallery[0]['url']);
        $description = (string) $response->json('data.game.description');
        $this->assertStringNotContainsString('<script>', $description);
    }

    public function test_submit_review_requires_auth(): void
    {
        $this->postJson('/api/storefront/v1/digital/reviews', [
            'stars' => 4,
            'comment' => 'Solid game',
            'game_id' => 42,
        ])->assertUnauthorized();
    }

    public function test_submit_review_proxies_to_accounts(): void
    {
        Http::fake([
            'accounts.test/api/reviews' => Http::response([
                'message' => 'Review submitted and pending approval.',
            ], 201),
        ]);

        $auth = $this->registerAndLoginForDigitalReview();

        $this->withHeader('Authorization', 'Bearer '.$auth['token'])
            ->postJson('/api/storefront/v1/digital/reviews', [
                'stars' => 4,
                'comment' => 'Solid game',
                'game_id' => 42,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        Http::assertSent(function ($request) use ($auth) {
            return $request->url() === 'https://accounts.test/api/reviews'
                && $request->method() === 'POST'
                && ($request['game_id'] ?? null) === 42
                && ($request['stars'] ?? null) === 4
                && ($request['phone'] ?? null) === $auth['mobile'];
        });
    }

    public function test_submit_review_requires_exactly_one_target(): void
    {
        $auth = $this->registerAndLoginForDigitalReview();

        $this->withHeader('Authorization', 'Bearer '.$auth['token'])
            ->postJson('/api/storefront/v1/digital/reviews', [
                'stars' => 5,
                'game_id' => 1,
                'card_category_id' => 2,
            ])->assertStatus(422);
    }

    /**
     * @return array{token: string, mobile: string}
     */
    private function registerAndLoginForDigitalReview(): array
    {
        $email = 'digital_review_'.uniqid().'@example.com';
        $mobile = '+2010'.random_int(10000000, 99999999);

        $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Digital',
            'last_name' => 'Reviewer',
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
            'token' => (string) $login->json('data.token'),
            'mobile' => $mobile,
        ];
    }

    public function test_check_stock_accepts_full_type(): void
    {
        Http::fake([
            'accounts.test/api/login' => Http::response(['token' => 'test-token'], 200),
            'accounts.test/api/orders/check_stock' => Http::response([
                'is_available' => true,
                'stock' => 2,
            ], 200),
        ]);

        $this->postJson('/api/storefront/v1/digital/check-stock', [
            'game_id' => 42,
            'type' => 'full',
            'platform' => '5',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_check_card_stock_maps_to_accounts_category_id(): void
    {
        Http::fake([
            'accounts.test/api/login' => Http::response(['token' => 'test-token'], 200),
            'accounts.test/api/orders/check_card_stock' => Http::response([
                'stock' => 5,
                'is_available' => true,
            ], 200),
        ]);

        $this->postJson('/api/storefront/v1/digital/check-card-stock', [
            'card_category_id' => 3,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://accounts.test/api/orders/check_card_stock'
                && $request->method() === 'POST'
                && ($request['category_id'] ?? null) === 3
                && ! array_key_exists('card_category_id', $request->data());
        });
    }
}
