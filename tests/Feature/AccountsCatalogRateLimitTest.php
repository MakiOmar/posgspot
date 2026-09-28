<?php

namespace Tests\Feature;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * An Accounts catalog backfill sends one request per game, so the route must not share the 60/min api budget.
 */
class AccountsCatalogRateLimitTest extends TestCase
{
    public function test_catalog_upsert_uses_its_own_limiter_instead_of_the_api_limit(): void
    {
        $route = Route::getRoutes()->match(Request::create('/api/accounts/catalog/upsert/1', 'POST'));
        $middleware = Route::gatherRouteMiddleware($route);

        $this->assertContains(ThrottleRequests::class.':accounts-catalog', $middleware);
        $this->assertNotContains(ThrottleRequests::class.':api', $middleware);
        $this->assertContains('auth:api', $route->middleware());
    }

    public function test_catalog_limit_follows_config(): void
    {
        config(['services.accounts.catalog_rate_limit_per_minute' => 450]);

        $limit = RateLimiter::limiter('accounts-catalog')(Request::create('/api/accounts/catalog/upsert/1', 'POST'));

        $this->assertInstanceOf(Limit::class, $limit);
        $this->assertSame(450, $limit->maxAttempts);
    }
}
