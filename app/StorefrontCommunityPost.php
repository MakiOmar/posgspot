<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public const REGISTRATION_OFF = 'off';

    public const REGISTRATION_INTERNAL = 'internal';

    public const REGISTRATION_EXTERNAL = 'external';

    public const TYPES = [
        self::TYPE_TOURNAMENT,
        self::TYPE_EVENT,
        self::TYPE_NEWS,
    ];

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
    ];

    public const REGISTRATION_MODES = [
        self::REGISTRATION_OFF,
        self::REGISTRATION_INTERNAL,
        self::REGISTRATION_EXTERNAL,
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'is_featured' => 'boolean',
        'registration_open' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(StorefrontCommunityPostTranslation::class, 'community_post_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(StorefrontCommunityPostMedia::class, 'community_post_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(StorefrontCommunityApplication::class, 'community_post_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(BusinessLocation::class, 'location_id');
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

    public function acceptsInternalApplications(): bool
    {
        return $this->registration_open
            && $this->registration_mode === self::REGISTRATION_INTERNAL
            && in_array($this->type, [self::TYPE_TOURNAMENT, self::TYPE_EVENT], true);
    }
}
