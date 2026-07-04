<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api.php'));

            // Intentionally omit the `api` group (which includes throttle:api at 60/min).
            // Qwik SSR issues many GETs from one IP; storefront uses its own limiter only.
            Route::prefix('api')
                ->middleware([
                    \App\Http\Middleware\FixAuthorizationHeader::class,
                    \Illuminate\Routing\Middleware\SubstituteBindings::class,
                    'storefront.business',
                    'storefront.content.locale',
                    'throttle:storefront',
                ])
                ->group(base_path('routes/storefront.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('storefront', function (Request $request) {
            // Safe methods: shell loaders (settings/categories) + catalog reads from SSR.
            // Mutations (auth, checkout, contact) stay on the stricter write budget.
            $perMinute = $request->isMethodSafe()
                ? (int) config('storefront.rate_limit_read_per_minute', 600)
                : (int) config('storefront.rate_limit_per_minute', 120);

            return Limit::perMinute(max(1, $perMinute))->by($request->ip());
        });
    }
}
