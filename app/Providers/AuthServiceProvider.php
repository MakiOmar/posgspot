<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Passport::tokensCan([
            'user-read' => 'Read the authenticated integration user profile',
            'accounts-orders' => 'Create and undo Accounts-synced orders',
            'accounts-catalog' => 'Upsert Accounts catalog products',
            'contacts-read' => 'Read contact PII for the token business',
            'rewards' => 'Validate and redeem reward points',
        ]);

        Passport::personalAccessTokensExpireIn(
            now()->addDays((int) config('api.token_expire_days', 7))
        );

        Gate::before(function ($user, $ability) {
            if (in_array($ability, ['backup', 'superadmin',
                'manage_modules', ])) {
                $administrator_list = config('constants.administrator_usernames');

                if (in_array(strtolower($user->username), explode(',', strtolower($administrator_list)))) {
                    return true;
                }
            } else {
                if ($user->hasRole('Admin#'.$user->business_id)) {
                    return true;
                }
            }
        });
    }
}
