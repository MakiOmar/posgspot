<?php

namespace Tests\Feature\Storefront;

use App\StorefrontCommunityPost;
use App\StorefrontCommunityPostTranslation;
use Tests\TestCase;

class CommunityPostTest extends TestCase
{
    protected int $businessId = 1;

    protected function setUp(): void
    {
        parent::setUp();
        config(['storefront.community.enabled' => true]);
    }

    private function seedPublishedPost(string $slug = 'summer-cup'): StorefrontCommunityPost
    {
        $post = StorefrontCommunityPost::create([
            'business_id' => $this->businessId,
            'type' => StorefrontCommunityPost::TYPE_TOURNAMENT,
            'slug' => $slug,
            'status' => StorefrontCommunityPost::STATUS_PUBLISHED,
            'starts_at' => now()->addDays(3),
            'published_at' => now(),
        ]);

        StorefrontCommunityPostTranslation::create([
            'community_post_id' => $post->id,
            'locale' => 'en',
            'title' => 'Summer Cup',
            'excerpt' => 'Join us',
            'body' => '<p>Details</p>',
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

        $this->getJson('/api/storefront/v1/community/posts', ['X-Content-Locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.0.slug', $post->slug)
            ->assertJsonPath('data.0.title', 'Summer Cup');

        $this->getJson('/api/storefront/v1/community/posts/'.$post->slug, ['X-Content-Locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.body', '<p>Details</p>');

        $this->getJson('/api/storefront/v1/community/posts', ['X-Content-Locale' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_upcoming_scope_filters_past_events(): void
    {
        $future = StorefrontCommunityPost::create([
            'business_id' => $this->businessId,
            'type' => StorefrontCommunityPost::TYPE_EVENT,
            'slug' => 'future-event',
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

        StorefrontCommunityPost::create([
            'business_id' => $this->businessId,
            'type' => StorefrontCommunityPost::TYPE_EVENT,
            'slug' => 'past-event',
            'status' => StorefrontCommunityPost::STATUS_PUBLISHED,
            'starts_at' => now()->subDay(),
            'published_at' => now(),
        ])->tap(function (StorefrontCommunityPost $post) {
            StorefrontCommunityPostTranslation::create([
                'community_post_id' => $post->id,
                'locale' => 'en',
                'title' => 'Past',
                'excerpt' => null,
                'body' => null,
            ]);
        });

        $response = $this->getJson('/api/storefront/v1/community/posts?type=event&scope=upcoming', [
            'X-Content-Locale' => 'en',
        ])->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->all();
        $this->assertContains('future-event', $slugs);
        $this->assertNotContains('past-event', $slugs);
    }
}
