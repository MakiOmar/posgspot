<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontCommunityPostMedia extends Model
{
    public const KIND_IMAGE = 'image';

    public const KIND_VIDEO = 'video';

    protected $table = 'storefront_community_post_media';

    protected $guarded = ['id'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(StorefrontCommunityPost::class, 'community_post_id');
    }

    public function publicUrl(int $businessId): ?string
    {
        $path = trim((string) ($this->path ?? ''));
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $file = basename(str_replace('\\', '/', $path));

        return asset('uploads/storefront_community/'.$businessId.'/'.$file);
    }
}
