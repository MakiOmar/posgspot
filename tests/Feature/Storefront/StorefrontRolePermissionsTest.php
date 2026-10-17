<?php

namespace Tests\Feature\Storefront;

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Storefront dashboard pages are grantable per role from POS Roles (settings, community, requests, reviews, coupons).
 */
class StorefrontRolePermissionsTest extends TestCase
{
    use DatabaseTransactions;

    private int $businessId = 1;

    private function admin(): User
    {
        $admin = User::where('business_id', $this->businessId)
            ->get()
            ->first(fn (User $user) => $user->hasRole('Admin#'.$this->businessId));
        if (! $admin) {
            $this->markTestSkipped('No admin user for business 1.');
        }

        return $admin;
    }

    /** Non-admin staff member holding only the given permissions through a custom role. */
    private function staffWith(array $permissions): User
    {
        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $role = Role::create([
            'name' => 'ZZ Storefront Staff '.uniqid().'#'.$this->businessId,
            'business_id' => $this->businessId,
            'guard_name' => 'web',
        ]);
        $role->syncPermissions($permissions);

        $user = User::forceCreate([
            'user_type' => 'user',
            'surname' => '',
            'first_name' => 'ZZ Storefront',
            'last_name' => 'Staff',
            'username' => 'zz_sf_staff_'.uniqid(),
            'email' => 'zz_sf_staff_'.uniqid().'@example.test',
            'password' => Hash::make('secret'),
            'language' => 'en',
            'business_id' => $this->businessId,
            'status' => 'active',
            'allow_login' => 1,
        ]);
        $user->assignRole($role->name);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function as(User $user)
    {
        return $this->actingAs($user);
    }

    public function test_role_form_lists_storefront_permissions_and_saves_them(): void
    {
        $admin = $this->admin();

        $this->as($admin)->get('/roles/create')
            ->assertOk()
            ->assertSee('value="storefront.settings"', false)
            ->assertSee('value="storefront.community"', false)
            ->assertSee('value="storefront.requests"', false)
            ->assertSee('value="product_review.moderate"', false)
            ->assertSee('value="coupon.delete"', false);

        $name = 'ZZ Reviews Moderator '.uniqid();
        $this->as($admin)->post('/roles', [
            'name' => $name,
            'permissions' => ['storefront.requests', 'product_review.access', 'product_review.moderate'],
        ])->assertRedirect('roles');

        $role = Role::where('name', $name.'#'.$this->businessId)->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['storefront.requests', 'product_review.access', 'product_review.moderate'],
            $role->permissions->pluck('name')->all()
        );

        $this->as($admin)->get('/roles/'.$role->id.'/edit')
            ->assertOk()
            ->assertSee('checked="checked" name="permissions[]" type="checkbox" value="storefront.requests"', false)
            ->assertDontSee('checked="checked" name="permissions[]" type="checkbox" value="storefront.settings"', false);
    }

    public function test_requests_permission_opens_requests_only(): void
    {
        $staff = $this->staffWith(['storefront.requests']);

        $this->as($staff)->get('/storefront/sell-requests')->assertOk();
        $this->as($staff)->get('/storefront/product-requests')->assertOk();
        $this->as($staff)->get('/storefront/community/applications')->assertOk();
        $this->as($staff)->get('/storefront/community')->assertForbidden();
        $this->as($staff)->get('/storefront/settings')->assertForbidden();
    }

    public function test_community_permission_opens_community_posts_only(): void
    {
        $staff = $this->staffWith(['storefront.community']);

        $this->as($staff)->get('/storefront/community')->assertOk();
        $this->as($staff)->get('/storefront/sell-requests')->assertForbidden();
        $this->as($staff)->get('/storefront/settings')->assertForbidden();
    }

    public function test_settings_permission_no_longer_implies_requests_or_community(): void
    {
        $staff = $this->staffWith(['storefront.settings']);

        $this->as($staff)->get('/storefront/settings')->assertOk();
        $this->as($staff)->get('/storefront/sell-requests')->assertForbidden();
        $this->as($staff)->get('/storefront/community')->assertForbidden();
    }
}
