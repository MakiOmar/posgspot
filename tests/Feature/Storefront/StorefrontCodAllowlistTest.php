<?php

namespace Tests\Feature\Storefront;

use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\Jobs\NotifyStaffOfStorefrontOrder;
use App\Product;
use App\Services\Storefront\CodEligibility;
use App\Services\Storefront\StorefrontSettingService;
use App\User;
use App\Variation;
use App\VariationLocationDetails;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Storefront settings → General: cash on delivery limited to allowlisted customer emails.
 */
class StorefrontCodAllowlistTest extends TestCase
{
    use DatabaseTransactions;

    private int $businessId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Business::whereKey($this->businessId)->exists()) {
            $this->markTestSkipped('No business in database.');
        }
    }

    public function test_save_normalizes_and_replaces_the_email_list(): void
    {
        $settings = app(StorefrontSettingService::class);
        $settings->save($this->businessId, [
            'cod_allowed_emails' => [' VIP@Example.com ', 'vip@example.com', 'not-an-email', 'other@example.com'],
        ]);
        Cache::flush();
        $this->assertSame(['vip@example.com', 'other@example.com'], $settings->get($this->businessId)['cod_allowed_emails']);

        $settings->save($this->businessId, ['cod_allowed_emails' => ['other@example.com']]);
        Cache::flush();
        $this->assertSame(['other@example.com'], $settings->get($this->businessId)['cod_allowed_emails']);
    }

    public function test_settings_page_shows_the_allowlist_with_saved_emails(): void
    {
        $this->saveCodSettings(false, ['saved.vip@example.com']);

        // No withSession(): the session middleware loads the full business session the layout needs.
        $this->actingAs($this->admin(['storefront.settings']))
            ->get(route('storefront.settings.edit'))
            ->assertOk()
            ->assertSee('Cash on Delivery allowed customers')
            ->assertSee('name="cod_allowed_emails[]"', false)
            ->assertSee('<option value="saved.vip@example.com" selected="selected">', false);
    }

    public function test_settings_update_rejects_invalid_emails(): void
    {
        $this->actingAs($this->admin(['storefront.settings']))
            ->withSession(['user.business_id' => $this->businessId])
            ->postJson(route('storefront.settings.update'), ['cod_allowed_emails' => ['nope']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cod_allowed_emails.0');
    }

    public function test_customer_search_returns_matching_emails(): void
    {
        $email = 'cod_search_'.uniqid().'@example.com';
        $this->customer($email, verified: false, name: 'Cod Search Person');

        $this->actingAs($this->admin(['storefront.settings']))
            ->withSession(['user.business_id' => $this->businessId])
            ->getJson(route('storefront.settings.cod-customer-emails', ['q' => 'Cod Search Person']))
            ->assertOk()
            ->assertJsonPath('results.0.id', $email)
            ->assertJsonPath('results.0.text', 'Cod Search Person — '.$email.' (not verified)');
    }

    public function test_customer_search_requires_storefront_settings_permission(): void
    {
        $this->actingAs($this->admin([]))
            ->withSession(['user.business_id' => $this->businessId])
            ->getJson(route('storefront.settings.cod-customer-emails', ['q' => 'example']))
            ->assertForbidden();
    }

    public function test_profile_flags_allowlisted_verified_customer(): void
    {
        $allowed = $this->customer('cod_vip_'.uniqid().'@example.com');
        $other = $this->customer('cod_regular_'.uniqid().'@example.com');
        $this->saveCodSettings(false, [strtoupper($allowed->email)]);

        $this->withHeader('Authorization', 'Bearer '.$allowed->createToken('test')->plainTextToken)
            ->getJson('/api/storefront/v1/account/profile')
            ->assertOk()
            ->assertJsonPath('data.cod_allowed', true);

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$other->createToken('test')->plainTextToken)
            ->getJson('/api/storefront/v1/account/profile')
            ->assertOk()
            ->assertJsonPath('data.cod_allowed', false);

        $public = $this->getJson('/api/storefront/v1/settings')->assertOk();
        $this->assertStringNotContainsString($allowed->email, $public->getContent());
        $public->assertJsonMissingPath('data.cod_allowed_emails');
    }

    public function test_checkout_cod_only_for_allowlisted_customers_when_cod_is_disabled(): void
    {
        Mail::fake();
        Queue::fake([NotifyStaffOfStorefrontOrder::class]);
        [$location, $variation] = $this->stockedVariation();

        $allowed = $this->customer('cod_vip_'.uniqid().'@example.com');
        $this->saveCodSettings(false, [$allowed->email], $location->id);
        $rateId = $this->firstShippingRateId($variation->id, $location->id);

        $this->postJson('/api/storefront/v1/checkout', $this->checkoutPayload($location->id, $variation->id, $rateId) + [
            'customer' => [
                'first_name' => 'Guest',
                'last_name' => 'Buyer',
                'email' => $allowed->email,
                'mobile' => '201012345678',
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('payment_method');

        $this->withHeader('Authorization', 'Bearer '.$allowed->createToken('test')->plainTextToken)
            ->postJson('/api/storefront/v1/checkout', $this->checkoutPayload($location->id, $variation->id, $rateId))
            ->assertCreated()
            ->assertJsonPath('success', true);
    }

    public function test_unverified_allowlisted_customer_cannot_use_cod(): void
    {
        $contact = $this->customer('cod_unverified_'.uniqid().'@example.com', verified: false);

        $this->assertFalse(CodEligibility::isAvailable([
            'cod_enabled' => false,
            'cod_allowed_emails' => [$contact->email],
        ], $contact));
    }

    private function saveCodSettings(bool $enabled, array $emails, ?int $locationId = null): void
    {
        $payload = ['cod_enabled' => $enabled, 'cod_allowed_emails' => $emails];
        if ($locationId) {
            $payload['selling_location_ids'] = [$locationId];
            $payload['default_fulfillment_location_id'] = $locationId;
        }
        app(StorefrontSettingService::class)->save($this->businessId, $payload);
        Cache::flush();
    }

    private function checkoutPayload(int $locationId, int $variationId, string $rateId): array
    {
        return [
            'idempotency_key' => 'SF-COD-'.uniqid(),
            'location_id' => $locationId,
            'payment_method' => 'cod',
            'items' => [['variation_id' => $variationId, 'quantity' => 1]],
            'shipping_address' => [
                'address_line_1' => '12 Test Street',
                'city' => 'Cairo',
                'state' => 'C',
                'country' => 'EG',
            ],
            'shipping_rate_id' => $rateId,
        ];
    }

    /**
     * @return array{0: BusinessLocation, 1: Variation}
     */
    private function stockedVariation(): array
    {
        $location = BusinessLocation::where('business_id', $this->businessId)->where('is_active', 1)->first();
        $product = Product::where('business_id', $this->businessId)
            ->where('is_inactive', 0)
            ->where('not_for_selling', 0)
            ->where('enable_stock', 1)
            ->first();
        $variation = $product
            ? Variation::where('product_id', $product->id)->whereNull('deleted_at')->first()
            : null;
        if (! $location || ! $variation) {
            $this->markTestSkipped('No active location with a stocked sellable product.');
        }

        VariationLocationDetails::updateOrCreate(
            ['variation_id' => $variation->id, 'location_id' => $location->id],
            ['product_id' => $product->id, 'product_variation_id' => $variation->product_variation_id, 'qty_available' => 25]
        );

        return [$location, $variation];
    }

    private function firstShippingRateId(int $variationId, int $locationId): string
    {
        app(\App\Services\Storefront\Shipping\ShippingLegacyMigrator::class)->ensureDefaultZones($this->businessId);
        $quoted = app(\App\Services\Storefront\Shipping\ShippingQuoteService::class)->quote(
            $this->businessId,
            100,
            [['variation_id' => $variationId, 'quantity' => 1]],
            ['country' => 'EG', 'state' => 'C'],
            null,
            $locationId,
            'en',
            false
        );
        $rateId = $quoted['available_rates'][0]['id'] ?? null;
        if (empty($rateId)) {
            $this->markTestSkipped('No shipping rates available for test.');
        }

        return $rateId;
    }

    private function customer(string $email, bool $verified = true, string $name = 'Cod Tester'): Contact
    {
        return Contact::create([
            'business_id' => $this->businessId,
            'type' => 'customer',
            'contact_status' => 'active',
            'name' => $name,
            'first_name' => $name,
            'email' => $email,
            'mobile' => '0100'.random_int(1000000, 9999999),
            'password' => Hash::make('password123'),
            'created_by' => 1,
            'email_verified_at' => $verified ? now() : null,
        ]);
    }

    private function admin(array $permissions): User
    {
        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $role = Role::create([
            'name' => 'ZZ Cod Allowlist '.uniqid().'#'.$this->businessId,
            'business_id' => $this->businessId,
            'guard_name' => 'web',
        ]);
        $role->syncPermissions($permissions);

        // Explicit id, reloaded after insert: some local dumps lost AUTO_INCREMENT on users.id.
        $id = (int) User::max('id') + 1;
        User::forceCreate([
            'id' => $id,
            'user_type' => 'user',
            'surname' => '',
            'first_name' => 'ZZ Cod',
            'last_name' => 'Allowlist',
            'username' => 'zz_cod_allowlist_'.uniqid(),
            'email' => 'zz_cod_allowlist_'.uniqid().'@example.test',
            'password' => Hash::make('secret'),
            'language' => 'en',
            'business_id' => $this->businessId,
            'status' => 'active',
            'allow_login' => 1,
        ]);
        $user = User::findOrFail($id);
        $user->assignRole($role->name);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}
