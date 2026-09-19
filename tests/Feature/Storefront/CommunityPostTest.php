<?php

namespace Tests\Feature\Storefront;

use App\StorefrontCommunityApplication;
use App\StorefrontCommunityPost;
use App\StorefrontCommunityPostMedia;
use App\StorefrontCommunityPostTranslation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CommunityPostTest extends TestCase
{
    use DatabaseTransactions;

    protected int $businessId = 1;

    protected function setUp(): void
    {
        parent::setUp();
        config(['storefront.community.enabled' => true]);
    }

    private function seedPublishedPost(string $slug = 'summer-cup', array $overrides = []): StorefrontCommunityPost
    {
        $uniqueSlug = ($overrides['slug'] ?? $slug).'-'.substr(uniqid(), -6);
        unset($overrides['slug']);

        $post = StorefrontCommunityPost::create(array_merge([
            'business_id' => $this->businessId,
            'type' => StorefrontCommunityPost::TYPE_TOURNAMENT,
            'slug' => $uniqueSlug,
            'status' => StorefrontCommunityPost::STATUS_PUBLISHED,
            'starts_at' => now()->addDays(3),
            'published_at' => now(),
            'registration_mode' => StorefrontCommunityPost::REGISTRATION_INTERNAL,
            'registration_open' => true,
            'is_featured' => false,
        ], $overrides));

        StorefrontCommunityPostTranslation::create([
            'community_post_id' => $post->id,
            'locale' => 'en',
            'title' => 'Summer Cup',
            'excerpt' => 'Join us',
            'body' => '<p>Details</p>',
            'game_title' => 'FIFA',
        ]);

        return $post;
    }

    public function test_community_unavailable_when_disabled(): void
    {
        config(['storefront.community.enabled' => false]);

        $this->getJson('/api/storefront/v1/community/posts')
            ->assertStatus(404);
    }

    public function test_settings_exposes_community_flag(): void
    {
        $this->getJson('/api/storefront/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.community.enabled', true);

        config(['storefront.community.enabled' => false]);

        $this->getJson('/api/storefront/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.community.enabled', false);
    }

    public function test_list_and_show_respect_locale(): void
    {
        $post = $this->seedPublishedPost();

        $list = $this->getJson('/api/storefront/v1/community/posts?type=tournament', ['X-Content-Locale' => 'en'])
            ->assertOk()
            ->json('data');
        $match = collect($list)->firstWhere('slug', $post->slug);
        $this->assertNotNull($match);
        $this->assertSame('Summer Cup', $match['title']);
        $this->assertSame('FIFA', $match['game_title']);

        $this->getJson('/api/storefront/v1/community/posts/'.$post->slug, ['X-Content-Locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.body', '<p>Details</p>');

        $arList = $this->getJson('/api/storefront/v1/community/posts?type=tournament', ['X-Content-Locale' => 'ar'])
            ->assertOk()
            ->json('data');
        $this->assertNull(collect($arList)->firstWhere('slug', $post->slug));
    }

    public function test_upcoming_scope_filters_past_events(): void
    {
        $suffix = substr(uniqid(), -6);
        $future = StorefrontCommunityPost::create([
            'business_id' => $this->businessId,
            'type' => StorefrontCommunityPost::TYPE_EVENT,
            'slug' => 'future-event-'.$suffix,
            'status' => StorefrontCommunityPost::STATUS_PUBLISHED,
            'starts_at' => now()->addDays(2),
            'published_at' => now(),
        ]);
        StorefrontCommunityPostTranslation::create([
            'community_post_id' => $future->id,
            'locale' => 'en',
            'title' => 'Future',
            'excerpt' => null,
            'body' => null,
        ]);

        $past = StorefrontCommunityPost::create([
            'business_id' => $this->businessId,
            'type' => StorefrontCommunityPost::TYPE_EVENT,
            'slug' => 'past-event-'.$suffix,
            'status' => StorefrontCommunityPost::STATUS_PUBLISHED,
            'starts_at' => now()->subDay(),
            'published_at' => now(),
        ]);
        StorefrontCommunityPostTranslation::create([
            'community_post_id' => $past->id,
            'locale' => 'en',
            'title' => 'Past',
            'excerpt' => null,
            'body' => null,
        ]);

        $response = $this->getJson('/api/storefront/v1/community/posts?type=event&scope=upcoming', [
            'X-Content-Locale' => 'en',
        ])->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->all();
        $this->assertContains('future-event-'.$suffix, $slugs);
        $this->assertNotContains('past-event-'.$suffix, $slugs);
    }

    public function test_detail_includes_media_and_related(): void
    {
        $post = $this->seedPublishedPost('main-news', [
            'type' => StorefrontCommunityPost::TYPE_NEWS,
            'is_featured' => true,
            'registration_mode' => StorefrontCommunityPost::REGISTRATION_OFF,
            'registration_open' => false,
            'starts_at' => null,
        ]);
        StorefrontCommunityPostMedia::create([
            'community_post_id' => $post->id,
            'kind' => 'image',
            'path' => 'gallery-a.jpg',
            'sort_order' => 0,
        ]);
        $related = $this->seedPublishedPost('related-news', [
            'type' => StorefrontCommunityPost::TYPE_NEWS,
            'registration_mode' => StorefrontCommunityPost::REGISTRATION_OFF,
            'registration_open' => false,
            'starts_at' => null,
        ]);

        $this->getJson('/api/storefront/v1/community/posts/'.$post->slug, ['X-Content-Locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.is_featured', true)
            ->assertJsonPath('data.media.0.kind', 'image')
            ->assertJsonPath('data.related_posts.0.slug', $related->slug);
    }

    public function test_apply_internal_registration(): void
    {
        $post = $this->seedPublishedPost('apply-cup');

        $this->postJson('/api/storefront/v1/community/posts/'.$post->slug.'/applications', [
            'name' => 'Ali',
            'mobile' => '01001234567',
            'source' => 'web',
        ], ['X-Content-Locale' => 'en'])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Ali')
            ->assertJsonPath('data.status', 'new');

        $this->assertDatabaseHas('storefront_community_applications', [
            'community_post_id' => $post->id,
            'mobile' => '01001234567',
            'status' => StorefrontCommunityApplication::STATUS_NEW,
        ]);
    }

    public function test_apply_rejects_duplicate_and_external_mode(): void
    {
        $post = $this->seedPublishedPost('dup-cup');

        $this->postJson('/api/storefront/v1/community/posts/'.$post->slug.'/applications', [
            'name' => 'Ali',
            'mobile' => '01009998887',
        ], ['X-Content-Locale' => 'en'])->assertStatus(201);

        $this->postJson('/api/storefront/v1/community/posts/'.$post->slug.'/applications', [
            'name' => 'Ali Again',
            'mobile' => '01009998887',
        ], ['X-Content-Locale' => 'en'])->assertStatus(422);

        $external = $this->seedPublishedPost('ext-cup', [
            'registration_mode' => StorefrontCommunityPost::REGISTRATION_EXTERNAL,
            'registration_url' => 'https://example.com/form',
            'registration_open' => true,
        ]);

        $this->postJson('/api/storefront/v1/community/posts/'.$external->slug.'/applications', [
            'name' => 'Bob',
            'mobile' => '01001112223',
        ], ['X-Content-Locale' => 'en'])->assertStatus(422);
    }
}
