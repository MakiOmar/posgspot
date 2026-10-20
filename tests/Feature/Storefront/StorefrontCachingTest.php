<?php

namespace Tests\Feature\Storefront;

use App\Category;
use App\CategoryTranslation;
use App\Services\Storefront\Accounts\AccountsApiClient;
use App\Services\Storefront\CatalogService;
use App\Support\StorefrontLocale;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Storefront API caching: locale-aware public cache headers, category tree
 * invalidation, and the short shared cache in front of Accounts catalog reads.
 */
class StorefrontCachingTest extends TestCase
{
    use DatabaseTransactions;

    protected int $businessId = 1;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_public_cache_responses_vary_on_locale_headers_and_etag_per_locale(): void
    {
        $en = $this->getJson('/api/storefront/v1/settings', ['X-Content-Locale' => 'en'])->assertOk();
        $ar = $this->getJson('/api/storefront/v1/settings', ['X-Content-Locale' => 'ar'])->assertOk();

        $vary = strtolower((string) $en->headers->get('Vary'));
        $this->assertStringContainsString('x-content-locale', $vary);
        $this->assertStringContainsString('accept-language', $vary);
        $this->assertStringContainsString('public', (string) $en->headers->get('Cache-Control'));
        $this->assertNotSame($en->headers->get('ETag'), $ar->headers->get('ETag'));
    }

    public function test_matching_etag_returns_not_modified(): void
    {
        $first = $this->getJson('/api/storefront/v1/settings', ['X-Content-Locale' => 'en'])->assertOk();
        $etag = (string) $first->headers->get('ETag');

        $this->getJson('/api/storefront/v1/settings', ['X-Content-Locale' => 'en', 'If-None-Match' => $etag])
            ->assertStatus(304);
    }

    public function test_saving_a_category_forgets_cached_trees_for_every_locale(): void
    {
        $this->seedCategoryCache();

        $category = Category::create([
            'name' => 'Cache Probe '.uniqid(),
            'business_id' => $this->businessId,
            'parent_id' => 0,
            'created_by' => 1,
            'category_type' => 'product',
        ]);

        $this->assertCategoryCacheEmpty();

        $this->seedCategoryCache();
        $category->delete();
        $this->assertCategoryCacheEmpty();
    }

    public function test_saving_a_category_translation_forgets_cached_trees(): void
    {
        $category = Category::create([
            'name' => 'Cache Probe '.uniqid(),
            'business_id' => $this->businessId,
            'parent_id' => 0,
            'created_by' => 1,
            'category_type' => 'product',
        ]);
        $this->seedCategoryCache();

        CategoryTranslation::create([
            'category_id' => $category->id,
            'locale' => 'ar',
            'name' => 'فحص',
            'slug' => 'cache-probe-'.uniqid(),
        ]);

        $this->assertCategoryCacheEmpty();
    }

    public function test_accounts_featured_games_are_cached_after_success_only(): void
    {
        config(['services.accounts.base' => 'https://accounts.test']);
        Http::fakeSequence('accounts.test/api/games/featured*')
            ->push(['message' => 'down'], 503)
            ->push(['count' => 1, '4' => [], '5' => []], 200)
            ->push(['count' => 1, '4' => [['id' => 99]], '5' => []], 200);

        $client = app(AccountsApiClient::class);

        $this->assertFalse($client->getFeaturedGames(4)['success']);
        $this->assertTrue($client->getFeaturedGames(4)['success']);
        $cached = $client->getFeaturedGames(4);

        $this->assertSame([], $cached['body']['4']);
        Http::assertSentCount(2);
    }

    public function test_accounts_platform_listing_cache_is_keyed_by_query(): void
    {
        config(['services.accounts.base' => 'https://accounts.test']);
        Http::fake(['accounts.test/api/games/platform/*' => Http::response(['data' => []], 200)]);

        $client = app(AccountsApiClient::class);
        $client->getGamesByPlatform('4', 1);
        $client->getGamesByPlatform('4', 1);
        $client->getGamesByPlatform('4', 2);
        $client->getGamesByPlatform('4', 1, 'fifa');

        Http::assertSentCount(3);
    }

    private function seedCategoryCache(): void
    {
        foreach (StorefrontLocale::SUPPORTED as $locale) {
            Cache::put('storefront.categories.'.$this->businessId.'.'.$locale, ['stale'], 120);
        }
    }

    private function assertCategoryCacheEmpty(): void
    {
        foreach (StorefrontLocale::SUPPORTED as $locale) {
            $this->assertNull(
                Cache::get('storefront.categories.'.$this->businessId.'.'.$locale),
                "categories cache for {$locale} should be forgotten"
            );
        }
    }

    public function test_forget_categories_cache_helper_clears_all_locales(): void
    {
        $this->seedCategoryCache();
        CatalogService::forgetCategoriesCache($this->businessId);
        $this->assertCategoryCacheEmpty();
    }
}
