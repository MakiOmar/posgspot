<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StorefrontCommunityPost extends Model
{
    use SoftDeletes;

    public const TYPE_TOURNAMENT = 'tournament';

    public const TYPE_EVENT = 'event';

    public const TYPE_NEWS = 'news';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const TYPES = [
        self::TYPE_TOURNAMENT,
        self::TYPE_EVENT,
        self::TYPE_NEWS,
    ];

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(StorefrontCommunityPostTranslation::class, 'community_post_id');
    }

    public function translationFor(string $locale): ?StorefrontCommunityPostTranslation
    {
        if ($this->relationLoaded('translations')) {
            return $this->translations->firstWhere('locale', $locale);
        }

        return $this->translations()->where('locale', $locale)->first();
    }

    public function coverUrl(): ?string
    {
        $path = trim((string) ($this->cover_path ?? ''));
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $file = basename(str_replace('\\', '/', $path));

        return asset('uploads/storefront_community/'.$this->business_id.'/'.$file);
    }
}
