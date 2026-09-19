<?php

namespace App\Services\Storefront;

use App\StorefrontCommunityApplication;
use App\StorefrontCommunityPost;
use App\StorefrontCommunityPostMedia;
use App\Support\StorefrontLocale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Community CMS posts (tournaments, events, news) for the public storefront API.
 */
class CommunityPostService
{
    public function __construct(private StorefrontHtmlSanitizer $htmlSanitizer)
    {
    }

    public function isEnabled(): bool
    {
        return (bool) config('storefront.community.enabled', false);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(
        int $businessId,
        string $locale,
        ?string $type = null,
        ?string $scope = null,
        ?string $q = null
    ): array {
        $locale = $this->normalizeLocale($locale);
        $query = $this->publishedQuery($businessId, $locale);

        if ($type !== null && $type !== '') {
            $query->where('type', $type);
        }

        $this->applyEventScope($query, $type, $scope);

        $q = trim((string) $q);
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->whereHas('translations', function (Builder $tq) use ($locale, $like) {
                $tq->where('locale', $locale)
                    ->where(function (Builder $inner) use ($like) {
                        $inner->where('title', 'like', $like)
                            ->orWhere('excerpt', 'like', $like)
                            ->orWhere('game_title', 'like', $like);
                    });
            });
        }

        if (in_array($type, [StorefrontCommunityPost::TYPE_TOURNAMENT, StorefrontCommunityPost::TYPE_EVENT], true)) {
            if ($scope === 'previous') {
                $query->orderByDesc('starts_at')->orderByDesc('published_at');
            } else {
                $query->orderBy('starts_at')->orderByDesc('published_at');
            }
        } else {
            $query->orderByDesc('is_featured')->orderByDesc('published_at')->orderByDesc('id');
        }

        $rows = $query
            ->with([
                'translations' => fn ($q2) => $q2->where('locale', $locale),
                'location',
            ])
            ->get();

        return $rows->map(fn (StorefrontCommunityPost $row) => $this->present($row, $locale, false))->values()->all();
    }

    public function showBySlug(int $businessId, string $locale, string $slug): ?array
    {
        $locale = $this->normalizeLocale($locale);
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $row = $this->publishedQuery($businessId, $locale)
            ->where('slug', $slug)
            ->with([
                'translations' => fn ($q) => $q->where('locale', $locale),
                'location',
                'media',
            ])
            ->first();

        if ($row === null) {
            return null;
        }

        return $this->present($row, $locale, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function apply(
        int $businessId,
        string $locale,
        string $slug,
        string $name,
        string $mobile,
        string $source = StorefrontCommunityApplication::SOURCE_WEB,
        ?int $contactId = null
    ): array {
        $locale = $this->normalizeLocale($locale);
        $post = $this->publishedQuery($businessId, $locale)
            ->where('slug', trim($slug))
            ->first();

        if ($post === null) {
            throw ValidationException::withMessages([
                'slug' => ['Post not found.'],
            ]);
        }

        if (! in_array($post->type, [StorefrontCommunityPost::TYPE_TOURNAMENT, StorefrontCommunityPost::TYPE_EVENT], true)) {
            throw ValidationException::withMessages([
                'slug' => ['Post not found.'],
            ]);
        }

        if (! $post->acceptsInternalApplications()) {
            throw ValidationException::withMessages([
                'registration' => ['Registration is not open for this post.'],
            ]);
        }

        $name = trim($name);
        $mobile = preg_replace('/\s+/', '', trim($mobile)) ?? '';
        if ($name === '' || $mobile === '') {
            throw ValidationException::withMessages([
                'name' => ['Name and mobile are required.'],
            ]);
        }

        $duplicate = StorefrontCommunityApplication::query()
            ->where('community_post_id', $post->id)
            ->where('mobile', $mobile)
            ->whereIn('status', [
                StorefrontCommunityApplication::STATUS_NEW,
                StorefrontCommunityApplication::STATUS_REVIEWED,
                StorefrontCommunityApplication::STATUS_ACCEPTED,
            ])
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'mobile' => ['You have already applied for this event with this mobile number.'],
            ]);
        }

        $application = StorefrontCommunityApplication::create([
            'business_id' => $businessId,
            'community_post_id' => $post->id,
            'name' => $name,
            'mobile' => $mobile,
            'locale' => $locale,
            'source' => in_array($source, [
                StorefrontCommunityApplication::SOURCE_WEB,
                StorefrontCommunityApplication::SOURCE_MOBILE,
            ], true) ? $source : StorefrontCommunityApplication::SOURCE_WEB,
            'contact_id' => $contactId ?: null,
            'status' => StorefrontCommunityApplication::STATUS_NEW,
        ]);

        return [
            'id' => $application->id,
            'status' => $application->status,
            'name' => $application->name,
            'mobile' => $application->mobile,
            'post_slug' => $post->slug,
            'post_type' => $post->type,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function present(StorefrontCommunityPost $row, string $locale, bool $includeFullBody = true): array
    {
        $locale = $this->normalizeLocale($locale);
        $translation = $row->translationFor($locale);
        if ($translation === null && $row->relationLoaded('translations')) {
            $translation = $row->translations->firstWhere('locale', $locale);
        }

        $title = $translation?->title ?? '';
        $excerpt = $translation?->excerpt ?? '';
        $gameTitle = $translation?->game_title ?? null;

        $location = null;
        if ($row->relationLoaded('location') && $row->location) {
            $location = [
                'id' => (int) $row->location->id,
                'name' => (string) ($row->location->name ?? ''),
            ];
        } elseif ($row->location_id) {
            $row->loadMissing('location');
            if ($row->location) {
                $location = [
                    'id' => (int) $row->location->id,
                    'name' => (string) ($row->location->name ?? ''),
                ];
            }
        }

        $payload = [
            'id' => $row->id,
            'type' => $row->type,
            'slug' => $row->slug,
            'title' => $title,
            'excerpt' => $excerpt,
            'cover_url' => $row->coverUrl(),
            'starts_at' => optional($row->starts_at)?->toIso8601String(),
            'ends_at' => optional($row->ends_at)?->toIso8601String(),
            'published_at' => optional($row->published_at)?->toIso8601String(),
            'is_featured' => (bool) $row->is_featured,
            'game_title' => $gameTitle,
            'prize_pool' => $row->prize_pool,
            'entry_fee' => $row->entry_fee,
            'available_spots' => $row->available_spots !== null ? (int) $row->available_spots : null,
            'registration_mode' => $row->registration_mode ?: StorefrontCommunityPost::REGISTRATION_OFF,
            'registration_open' => (bool) $row->registration_open,
            'winner' => $row->winner,
            'location' => $location,
        ];

        if ($includeFullBody) {
            $payload['body'] = $this->htmlSanitizer->sanitize((string) ($translation?->body ?? ''));
            $payload['rules'] = $this->htmlSanitizer->sanitize((string) ($translation?->rules ?? ''));
            $payload['results'] = $this->htmlSanitizer->sanitize((string) ($translation?->results ?? ''));
            $payload['highlights'] = $this->htmlSanitizer->sanitize((string) ($translation?->highlights ?? ''));
            $payload['recap'] = $this->htmlSanitizer->sanitize((string) ($translation?->recap ?? ''));
            $payload['registration_details'] = trim((string) ($translation?->registration_details ?? '')) ?: null;
            $payload['registration_url'] = $row->registration_mode === StorefrontCommunityPost::REGISTRATION_EXTERNAL
                ? ($row->registration_url ?: null)
                : null;
            $payload['media'] = $this->presentMedia($row);
            $payload['related_posts'] = $this->relatedPosts($row, $locale);
        }

        return $payload;
    }

    public function localeFromRequest(Request $request): string
    {
        return StorefrontLocale::fromRequest($request);
    }

    private function normalizeLocale(string $locale): string
    {
        $locale = strtolower(substr(trim($locale), 0, 2));

        return in_array($locale, StorefrontLocale::SUPPORTED, true) ? $locale : StorefrontLocale::DEFAULT;
    }

    private function publishedQuery(int $businessId, string $locale): Builder
    {
        return StorefrontCommunityPost::query()
            ->where('business_id', $businessId)
            ->where('status', StorefrontCommunityPost::STATUS_PUBLISHED)
            ->whereHas('translations', function (Builder $q) use ($locale) {
                $q->where('locale', $locale)
                    ->where('title', '!=', '');
            });
    }

    private function applyEventScope(Builder $query, ?string $type, ?string $scope): void
    {
        if ($scope === null || $scope === '') {
            return;
        }

        if (! in_array($type, [StorefrontCommunityPost::TYPE_TOURNAMENT, StorefrontCommunityPost::TYPE_EVENT], true)) {
            return;
        }

        $now = now();
        if ($scope === 'upcoming') {
            $query->where(function (Builder $q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '>=', $now);
            });
        } elseif ($scope === 'previous') {
            $query->whereNotNull('starts_at')->where('starts_at', '<', $now);
        }
    }

    /**
     * @return list<array{kind: string, url: string, caption: string|null}>
     */
    private function presentMedia(StorefrontCommunityPost $row): array
    {
        if (! $row->relationLoaded('media')) {
            $row->load('media');
        }

        $out = [];
        /** @var StorefrontCommunityPostMedia $item */
        foreach ($row->media as $item) {
            $url = $item->publicUrl((int) $row->business_id);
            if ($url === null) {
                continue;
            }
            $out[] = [
                'kind' => $item->kind === StorefrontCommunityPostMedia::KIND_VIDEO
                    ? StorefrontCommunityPostMedia::KIND_VIDEO
                    : StorefrontCommunityPostMedia::KIND_IMAGE,
                'url' => $url,
                'caption' => $item->caption,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function relatedPosts(StorefrontCommunityPost $row, string $locale): array
    {
        $related = $this->publishedQuery((int) $row->business_id, $locale)
            ->where('type', $row->type)
            ->where('id', '!=', $row->id)
            ->with([
                'translations' => fn ($q) => $q->where('locale', $locale),
                'location',
            ])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(4)
            ->get();

        return $related->map(fn (StorefrontCommunityPost $item) => $this->present($item, $locale, false))->values()->all();
    }
}
