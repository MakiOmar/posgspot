<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StorefrontSellRequest extends Model
{
    use SoftDeletes;

    public const TYPE_ACCOUNT = 'account';

    public const TYPE_DISC = 'disc';

    public const TYPE_DEVICE = 'device';

    public const STATUS_NEW = 'new';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_CONTACTED,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
        self::STATUS_CLOSED,
    ];

    public const TYPES = [
        self::TYPE_ACCOUNT,
        self::TYPE_DISC,
        self::TYPE_DEVICE,
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'purchased_from_us' => 'boolean',
        'details' => 'array',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(StorefrontSellRequestMedia::class, 'sell_request_id')->orderBy('sort_order');
    }
}
