<?php

namespace Tests\Feature\Storefront;

use App\Business;
use App\Mail\StorefrontTestEmail;
use App\Services\Storefront\StorefrontMailService;
use App\Services\Storefront\StorefrontSettingService;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Storefront settings → Contact: "Send test email" per notification inbox.
 */
class StorefrontTestEmailTest extends TestCase
{
    use DatabaseTransactions;

    private int $businessId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Business::whereKey($this->businessId)->exists()) {
            $this->markTestSkipped('No business in database.');
        }

        // .env mailer that would swallow the message if business settings were ignored.
        config(['mail.default' => 'log']);
        Business::whereKey($this->businessId)->update(['email_settings' => json_encode([
            'mail_driver' => 'array',
            'mail_host' => 'smtp.business.test',
            'mail_port' => 587,
            'mail_username' => 'shop@business.test',
            'mail_password' => 'secret',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'shop@business.test',
            'mail_from_name' => 'Business Shop',
            'use_superadmin_settings' => 0,
        ])]);
        app(StorefrontSettingService::class)->save($this->businessId, [
            'orders' => ['notify_email' => 'orders@business.test'],
            'sell_to_us' => ['notify_email' => ''],
        ]);
        Cache::flush();
    }

    public function test_sends_to_the_typed_address_with_business_mail_settings(): void
    {
        $this->actingAs($this->admin(['storefront.settings']))
            ->withSession(['user.business_id' => $this->businessId])
            ->postJson(route('storefront.settings.test-email'), ['type' => 'orders', 'email' => 'typed@business.test'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $sent = $this->sentMessages();
        $this->assertCount(1, $sent);
        $message = $sent->first()->getOriginalMessage();
        $this->assertSame('typed@business.test', $message->getTo()[0]->getAddress());
        $this->assertSame('shop@business.test', $message->getFrom()[0]->getAddress());
    }

    public function test_empty_field_uses_the_inbox_live_mail_goes_to(): void
    {
        $this->actingAs($this->admin(['storefront.settings']))
            ->withSession(['user.business_id' => $this->businessId])
            ->postJson(route('storefront.settings.test-email'), ['type' => 'orders', 'email' => ''])
            ->assertOk();
        $this->assertSame('orders@business.test', $this->sentMessages()->first()->getOriginalMessage()->getTo()[0]->getAddress());

        $this->actingAs($this->admin(['storefront.settings']))
            ->withSession(['user.business_id' => $this->businessId])
            ->postJson(route('storefront.settings.test-email'), ['type' => 'sell_to_us'])
            ->assertOk();
        $this->assertSame('shop@business.test', $this->sentMessages()->last()->getOriginalMessage()->getTo()[0]->getAddress());
    }

    public function test_transport_error_is_returned_to_the_admin(): void
    {
        $this->mock(StorefrontMailService::class, function ($mock) {
            $mock->shouldReceive('testRecipient')->andReturn('orders@business.test');
            $mock->shouldReceive('sendTestEmail')->andThrow(new \RuntimeException('Connection could not be established with host smtp.business.test'));
        });

        $this->actingAs($this->admin(['storefront.settings']))
            ->withSession(['user.business_id' => $this->businessId])
            ->postJson(route('storefront.settings.test-email'), ['type' => 'orders'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('msg', fn ($msg) => str_contains($msg, 'orders@business.test') && str_contains($msg, 'Connection could not be established'));
    }

    public function test_rejects_unknown_type_and_invalid_email(): void
    {
        $admin = $this->admin(['storefront.settings']);

        $this->actingAs($admin)->withSession(['user.business_id' => $this->businessId])
            ->postJson(route('storefront.settings.test-email'), ['type' => 'newsletter'])
            ->assertStatus(422)->assertJsonPath('success', false);
        $this->actingAs($admin)->withSession(['user.business_id' => $this->businessId])
            ->postJson(route('storefront.settings.test-email'), ['type' => 'orders', 'email' => 'not-an-email'])
            ->assertStatus(422)->assertJsonPath('success', false);

        $this->assertCount(0, $this->sentMessages());
    }

    public function test_requires_storefront_settings_permission(): void
    {
        $this->actingAs($this->admin([]))
            ->withSession(['user.business_id' => $this->businessId])
            ->postJson(route('storefront.settings.test-email'), ['type' => 'orders'])
            ->assertForbidden();

        $this->assertCount(0, $this->sentMessages());
    }

    public function test_settings_page_shows_test_buttons_and_current_inboxes(): void
    {
        // No withSession(): the session middleware loads the full business session the layout needs.
        $this->actingAs($this->admin(['storefront.settings']))
            ->get(route('storefront.settings.edit'))
            ->assertOk()
            ->assertSee('Send test email')
            ->assertSee('data-type="request_product"', false)
            ->assertSee('orders@business.test');
    }

    public function test_test_email_mailable_names_the_inbox_type(): void
    {
        $html = (new StorefrontTestEmail('orders', 'Business Shop', 'orders@business.test'))->render();

        $this->assertStringContainsString('New website orders', $html);
        $this->assertStringContainsString('orders@business.test', $html);
    }

    private function sentMessages()
    {
        return app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
    }

    private function admin(array $permissions): User
    {
        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $role = Role::create([
            'name' => 'ZZ Test Email '.uniqid().'#'.$this->businessId,
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
            'first_name' => 'ZZ Test',
            'last_name' => 'Email',
            'username' => 'zz_test_email_'.uniqid(),
            'email' => 'zz_test_email_'.uniqid().'@example.test',
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
