<?php

namespace Tests\Feature\Storefront;

use App\BusinessLocation;
use App\Product;
use App\Services\Storefront\StorefrontSettingService;
use App\StorefrontSetting;
use App\Variation;
use App\VariationLocationDetails;
use Tests\TestCase;

/**
 * Storefront API feature tests (catalog, auth, checkout).
 */
class StorefrontApiTest extends TestCase
{
    protected int $businessId = 1;

    public function test_ping_returns_ok(): void
    {
        $response = $this->getJson('/api/storefront/v1/ping');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.service', 'storefront-api');
    }

    public function test_products_empty_when_no_selling_locations(): void
    {
        StorefrontSetting::updateOrCreate(
            ['business_id' => $this->businessId],
            ['value' => ['selling_location_ids' => []]]
        );
        app(StorefrontSettingService::class);
        \Illuminate\Support\Facades\Cache::flush();

        $response = $this->getJson('/api/storefront/v1/products');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 0);
    }

    public function test_customer_can_register_and_login(): void
    {
        $email = 'storefront_test_'.uniqid().'@example.com';
        $mobile = '+2010'.random_int(10000000, 99999999);

        $register = $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => $email,
            'mobile' => $mobile,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $register->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'contact' => ['id', 'email']]]);

        $login = $this->postJson('/api/storefront/v1/auth/login', [
            'login' => $email,
            'password' => 'password123',
        ]);

        $login->assertOk()->assertJsonPath('success', true);
    }

    public function test_customer_can_reset_password_with_token(): void
    {
        $email = 'reset_test_'.uniqid().'@example.com';
        $mobile = '+2010'.random_int(10000000, 99999999);
        $plainToken = 'reset-token-'.uniqid();

        $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Reset',
            'last_name' => 'Test',
            'email' => $email,
            'mobile' => $mobile,
            'password' => 'oldpassword123',
            'password_confirmation' => 'oldpassword123',
        ])->assertCreated();

        \Illuminate\Support\Facades\DB::table('password_resets_contacts')->updateOrInsert(
            ['email' => $email],
            ['token' => \Illuminate\Support\Facades\Hash::make($plainToken), 'created_at' => now()]
        );

        $reset = $this->postJson('/api/storefront/v1/auth/reset-password', [
            'email' => $email,
            'token' => $plainToken,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $reset->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message', 'Password updated successfully.');

        $this->postJson('/api/storefront/v1/auth/login', [
            'login' => $email,
            'password' => 'newpassword123',
        ])->assertOk()->assertJsonPath('success', true);

        $this->postJson('/api/storefront/v1/auth/login', [
            'login' => $email,
            'password' => 'oldpassword123',
        ])->assertStatus(422);
    }

    public function test_expired_password_reset_token_is_rejected(): void
    {
        $email = 'reset_expired_'.uniqid().'@example.com';
        $mobile = '+2010'.random_int(10000000, 99999999);
        $plainToken = 'reset-token-'.uniqid();

        $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Reset',
            'last_name' => 'Expired',
            'email' => $email,
            'mobile' => $mobile,
            'password' => 'oldpassword123',
            'password_confirmation' => 'oldpassword123',
        ])->assertCreated();

        \Illuminate\Support\Facades\DB::table('password_resets_contacts')->updateOrInsert(
            ['email' => $email],
            [
                'token' => \Illuminate\Support\Facades\Hash::make($plainToken),
                'created_at' => now()->subMinutes((int) config('storefront.password_reset_expire_minutes', 60) + 1),
            ]
        );

        $this->postJson('/api/storefront/v1/auth/reset-password', [
            'email' => $email,
            'token' => $plainToken,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_settings_contact_does_not_expose_raw_email(): void
    {
        $email = 'contact_'.uniqid().'@example.com';

        app(StorefrontSettingService::class)->save($this->businessId, [
            'contact' => [
                'phone' => '01000000000',
                'email' => $email,
                'whatsapp' => '',
            ],
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        $response = $this->getJson('/api/storefront/v1/settings');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('data.contact.email')
            ->assertJsonPath('data.contact.email_encoded', base64_encode($email));
    }

    public function test_settings_exposes_catalog_availability_on_cards_flag(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'catalog' => ['show_availability_on_cards' => true],
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        $this->getJson('/api/storefront/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.catalog.show_availability_on_cards', true);

        app(StorefrontSettingService::class)->save($this->businessId, [
            'catalog' => ['show_availability_on_cards' => false],
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        $this->getJson('/api/storefront/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.catalog.show_availability_on_cards', false);
    }

    public function test_settings_exposes_promo_code_flags(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'promo_codes' => [
                'enabled_at_checkout' => false,
                'allow_stacking' => true,
            ],
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        $this->getJson('/api/storefront/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.promo_codes.enabled_at_checkout', false)
            ->assertJsonPath('data.promo_codes.allow_stacking', true);
    }

    public function test_settings_exposes_payment_icons(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'payment_icons' => [
                [
                    'label' => 'Visa',
                    'image' => null,
                    'url' => 'https://cdn.example.com/visa.svg',
                ],
                [
                    'label' => 'Cash on delivery',
                    'image' => 'cod.png',
                    'url' => '',
                ],
            ],
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        $this->getJson('/api/storefront/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.payment_icons.0.label', 'Visa')
            ->assertJsonPath('data.payment_icons.0.icon_url', 'https://cdn.example.com/visa.svg')
            ->assertJsonPath('data.payment_icons.1.label', 'Cash on delivery')
            ->assertJsonPath(
                'data.payment_icons.1.icon_url',
                asset('uploads/storefront_payment_icons/cod.png')
            );
    }

    public function test_settings_exposes_promotional_banners(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'banners' => [
                [
                    'id' => 'bn_home',
                    'placement' => 'home',
                    'category_slug' => '',
                    'title' => ['en' => 'Weekend sale', 'ar' => 'عرض نهاية الأسبوع'],
                    'link' => '/products',
                    'image' => null,
                    'url' => 'https://cdn.example.com/home-banner.webp',
                    'enabled' => true,
                    'sort_order' => 0,
                ],
                [
                    'id' => 'bn_cat',
                    'placement' => 'category',
                    'category_slug' => 'playstation',
                    'title' => ['en' => 'PS deals', 'ar' => ''],
                    'link' => 'https://example.com/ps',
                    'image' => null,
                    'url' => 'https://cdn.example.com/ps-banner.webp',
                    'enabled' => true,
                    'sort_order' => 1,
                ],
                [
                    'id' => 'bn_off',
                    'placement' => 'home',
                    'category_slug' => '',
                    'title' => ['en' => 'Hidden', 'ar' => ''],
                    'link' => '',
                    'image' => null,
                    'url' => 'https://cdn.example.com/hidden.webp',
                    'enabled' => false,
                    'sort_order' => 2,
                ],
            ],
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        $this->getJson('/api/storefront/v1/settings')
            ->assertOk()
            ->assertJsonCount(2, 'data.banners')
            ->assertJsonPath('data.banners.0.placement', 'home')
            ->assertJsonPath('data.banners.0.title', 'Weekend sale')
            ->assertJsonPath('data.banners.0.image_url', 'https://cdn.example.com/home-banner.webp')
            ->assertJsonPath('data.banners.1.placement', 'category')
            ->assertJsonPath('data.banners.1.category_slug', 'playstation');

        $this->getJson('/api/storefront/v1/settings', ['X-Content-Locale' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.banners.0.title', 'عرض نهاية الأسبوع');
    }

    public function test_locations_do_not_expose_raw_email(): void
    {
        $location = BusinessLocation::where('business_id', $this->businessId)->first();
        if (empty($location)) {
            $this->markTestSkipped('No business location in database.');
        }

        $testEmail = 'loc_'.uniqid().'@example.com';
        $location->email = $testEmail;
        $location->is_active = 1;
        $location->save();

        app(StorefrontSettingService::class)->save($this->businessId, [
            // Public /locations excludes selling locations — keep this branch off the selling list.
            'selling_location_ids' => [],
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        $response = $this->getJson('/api/storefront/v1/locations');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $rows = $response->json('data');
        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);
        $match = collect($rows)->first(fn ($row) => (int) ($row['id'] ?? 0) === (int) $location->id);
        $this->assertNotNull($match, 'Expected location to appear in /locations response.');
        $this->assertArrayNotHasKey('email', $match);
        $this->assertSame(base64_encode($testEmail), $match['email_encoded']);
        $this->assertFalse($match['is_selling_location'] ?? true);

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$location->id],
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        $public = $this->getJson('/api/storefront/v1/locations');
        $publicIds = collect($public->json('data'))->pluck('id')->map(fn ($id) => (int) $id);
        $this->assertFalse($publicIds->contains((int) $location->id), 'Selling locations must be excluded from default /locations.');

        $selling = $this->getJson('/api/storefront/v1/locations?selling_only=1');
        $sellingMatch = collect($selling->json('data'))->first(fn ($row) => (int) ($row['id'] ?? 0) === (int) $location->id);
        $this->assertNotNull($sellingMatch, 'Expected location in /locations?selling_only=1.');
        $this->assertTrue($sellingMatch['is_selling_location'] ?? false);
    }

    public function test_availability_endpoint_structure(): void
    {
        $location = BusinessLocation::where('business_id', $this->businessId)->first();
        if (empty($location)) {
            $this->markTestSkipped('No business location in database.');
        }

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$location->id],
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        $product = Product::where('business_id', $this->businessId)
            ->where('is_inactive', 0)
            ->where('not_for_selling', 0)
            ->first();

        if (empty($product)) {
            $this->markTestSkipped('No sellable product in database.');
        }

        $variation = Variation::where('product_id', $product->id)->whereNull('deleted_at')->first();
        if (empty($variation)) {
            $this->markTestSkipped('No variation for product.');
        }

        $url = '/api/storefront/v1/products/'.$product->id.'/availability?variation_id='.$variation->id;
        $response = $this->getJson($url);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'in_stock_count',
                    'cod_available',
                    'locations',
                ],
            ]);
    }

    public function test_cart_validate_checks_stock_at_fulfillment_location(): void
    {
        $locations = BusinessLocation::where('business_id', $this->businessId)
            ->where('is_active', 1)
            ->limit(2)
            ->get();

        if ($locations->count() < 2) {
            $this->markTestSkipped('Need at least two active business locations.');
        }

        [$locationA, $locationB] = $locations->all();

        $product = Product::where('business_id', $this->businessId)
            ->where('is_inactive', 0)
            ->where('not_for_selling', 0)
            ->where('enable_stock', 1)
            ->first();

        if (empty($product)) {
            $this->markTestSkipped('No stocked sellable product in database.');
        }

        $variation = Variation::where('product_id', $product->id)->whereNull('deleted_at')->first();
        if (empty($variation)) {
            $this->markTestSkipped('No variation for product.');
        }

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$locationA->id, $locationB->id],
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        VariationLocationDetails::updateOrCreate(
            ['variation_id' => $variation->id, 'location_id' => $locationA->id],
            ['product_id' => $product->id, 'product_variation_id' => $variation->product_variation_id, 'qty_available' => 10]
        );
        VariationLocationDetails::updateOrCreate(
            ['variation_id' => $variation->id, 'location_id' => $locationB->id],
            ['product_id' => $product->id, 'product_variation_id' => $variation->product_variation_id, 'qty_available' => 1]
        );

        $payload = [
            'location_id' => $locationB->id,
            'items' => [
                ['variation_id' => $variation->id, 'quantity' => 5],
            ],
        ];

        $this->postJson('/api/storefront/v1/cart/validate', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);

        $this->postJson('/api/storefront/v1/cart/validate', [
            'location_id' => $locationA->id,
            'items' => $payload['items'],
        ])->assertOk()->assertJsonPath('success', true);

        $resolve = $this->postJson('/api/storefront/v1/cart/validate', [
            'location_id' => $locationB->id,
            'resolve' => true,
            'items' => $payload['items'],
        ]);

        $resolve->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.line_status.0.max_quantity', 1)
            ->assertJsonPath('data.line_status.0.available', false)
            ->assertJsonPath('data.line_status.0.requested_quantity', 5);
    }

    public function test_contact_form_accepts_valid_payload(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $response = $this->postJson('/api/storefront/v1/contact', [
            'name' => 'Test User',
            'email' => 'visitor@example.com',
            'phone' => '+201012345678',
            'message' => 'Hello from the storefront contact form.',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['message']]);

        \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\StorefrontContactMessage::class);
    }

    public function test_locations_use_storefront_address_override(): void
    {
        $location = BusinessLocation::where('business_id', $this->businessId)->first();
        if (! $location) {
            $this->markTestSkipped('No business location seeded for business 1.');
        }

        $location->storefront_address = 'Mega Mall, 2nd Floor, New Cairo';
        $location->save();

        StorefrontSetting::updateOrCreate(
            ['business_id' => $this->businessId],
            ['value' => ['selling_location_ids' => []]]
        );
        \Illuminate\Support\Facades\Cache::flush();

        $response = $this->getJson('/api/storefront/v1/locations');

        $response->assertOk();
        $addresses = collect($response->json('data'))->pluck('address');
        $this->assertTrue($addresses->contains('Mega Mall, 2nd Floor, New Cairo'));
    }

    public function test_search_returns_empty_for_blank_query(): void
    {
        $this->getJson('/api/storefront/v1/search?q=')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', []);
    }

    public function test_search_returns_product_summaries_when_configured(): void
    {
        $location = BusinessLocation::where('business_id', $this->businessId)->first();
        if (empty($location)) {
            $this->markTestSkipped('No business location in database.');
        }

        $product = Product::where('business_id', $this->businessId)
            ->where('is_inactive', 0)
            ->where('not_for_selling', 0)
            ->first();

        if (empty($product)) {
            $this->markTestSkipped('No sellable product in database.');
        }

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$location->id],
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        $needle = mb_substr($product->name, 0, 4);
        if (mb_strlen($needle) < 2) {
            $this->markTestSkipped('Product name too short for search test.');
        }

        $response = $this->getJson('/api/storefront/v1/search?q='.urlencode($needle).'&limit=5');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'price']]]);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($product->id), 'Expected matching product in search results.');
    }

    public function test_product_returns_arabic_name_with_locale_header(): void
    {
        $location = BusinessLocation::where('business_id', $this->businessId)->first();
        if (empty($location)) {
            $this->markTestSkipped('No business location in database.');
        }

        $product = Product::where('business_id', $this->businessId)
            ->where('is_inactive', 0)
            ->where('not_for_selling', 0)
            ->first();

        if (empty($product)) {
            $this->markTestSkipped('No sellable product in database.');
        }

        $arName = 'منتج اختبار '.uniqid();

        \App\ProductTranslation::updateOrCreate(
            ['product_id' => $product->id, 'locale' => 'ar'],
            ['name' => $arName, 'slug' => 'ar-'.$product->id]
        );

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$location->id],
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        $response = $this->getJson(
            '/api/storefront/v1/products/'.$product->id,
            ['X-Content-Locale' => 'ar']
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', $arName);
    }

    public function test_product_detail_includes_related_products_from_same_category(): void
    {
        $location = BusinessLocation::where('business_id', $this->businessId)->first();
        if (empty($location)) {
            $this->markTestSkipped('No business location in database.');
        }

        $products = Product::where('business_id', $this->businessId)
            ->where('is_inactive', 0)
            ->where('not_for_selling', 0)
            ->whereNotNull('category_id')
            ->where('category_id', '>', 0)
            ->orderBy('id')
            ->limit(20)
            ->get();

        $primary = null;
        $related = null;
        foreach ($products as $candidate) {
            $peer = $products->first(
                fn (Product $p) => $p->id !== $candidate->id
                    && (int) $p->category_id === (int) $candidate->category_id
            );
            if ($peer) {
                $primary = $candidate;
                $related = $peer;
                break;
            }
        }

        if (empty($primary) || empty($related)) {
            $this->markTestSkipped('Need at least two sellable products in the same category.');
        }

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$location->id],
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        $response = $this->getJson('/api/storefront/v1/products/'.$primary->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'related_products' => [
                        ['id', 'name', 'slug', 'price', 'in_stock'],
                    ],
                ],
            ]);

        $relatedIds = collect($response->json('data.related_products'))->pluck('id');
        $this->assertFalse($relatedIds->contains($primary->id), 'Related list must exclude the current product.');
        $this->assertTrue(
            $relatedIds->contains($related->id),
            'Expected a same-category peer in related_products.'
        );
    }

    public function test_ar_product_list_excludes_untranslated_products(): void
    {
        $location = BusinessLocation::where('business_id', $this->businessId)->first();
        if (empty($location)) {
            $this->markTestSkipped('No business location in database.');
        }

        $product = Product::where('business_id', $this->businessId)
            ->where('is_inactive', 0)
            ->where('not_for_selling', 0)
            ->first();

        if (empty($product)) {
            $this->markTestSkipped('No sellable product in database.');
        }

        \App\ProductTranslation::where('product_id', $product->id)->where('locale', 'ar')->delete();

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$location->id],
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        $response = $this->getJson('/api/storefront/v1/products', ['X-Content-Locale' => 'ar']);

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($product->id), 'Untranslated product should not appear in AR list.');
    }
}
