<?php

namespace App\Services\Storefront;

use App\StorefrontCommunityPost;
use App\Support\StorefrontLocale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

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
    public function list(int $businessId, string $locale, ?string $type = null, ?string $scope = null): array
    {
        $locale = $this->normalizeLocale($locale);
        $query = $this->publishedQuery($businessId, $locale);

        if ($type !== null && $type !== '') {
            $query->where('type', $type);
        }

        $this->applyEventScope($query, $type, $scope);

        if (in_array($type, [StorefrontCommunityPost::TYPE_TOURNAMENT, StorefrontCommunityPost::TYPE_EVENT], true)) {
            if ($scope === 'previous') {
                $query->orderByDesc('starts_at')->orderByDesc('published_at');
            } else {
                $query->orderBy('starts_at')->orderByDesc('published_at');
            }
        } else {
            $query->orderByDesc('published_at')->orderByDesc('id');
        }

        $rows = $query->with(['translations' => fn ($q) => $q->where('locale', $locale)])->get();

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
            ->with(['translations' => fn ($q) => $q->where('locale', $locale)])
            ->first();

        if ($row === null) {
            return null;
        }

        return $this->present($row, $locale, true);
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
        $bodyRaw = $translation?->body ?? '';
        $body = $this->htmlSanitizer->sanitize($bodyRaw);

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
        ];

        if ($includeFullBody) {
            $payload['body'] = $body;
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
}
