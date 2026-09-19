<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontCommunityApplication extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const SOURCE_WEB = 'web';

    public const SOURCE_MOBILE = 'mobile';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_REVIEWED,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
    ];

    protected $table = 'storefront_community_applications';

    protected $guarded = ['id'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(StorefrontCommunityPost::class, 'community_post_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}
