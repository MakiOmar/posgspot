<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    public const TYPE_PERCENT_ORDER = 'percent_order';

    public const TYPE_FIXED_ORDER = 'fixed_order';

    public const TYPE_FREE_SHIPPING = 'free_shipping';

    public const APPLIES_ALL = 'all';

    public const APPLIES_CATEGORIES = 'categories';

    public const APPLIES_PRODUCTS = 'products';

    public const CHANNEL_STOREFRONT = 'storefront';

    public const CHANNEL_POS = 'pos';

    public const CHANNEL_BOTH = 'both';

    protected $guarded = ['id'];

    protected $casts = [
        'discount_amount' => 'float',
        'max_discount_amount' => 'float',
        'min_order_subtotal' => 'float',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'first_order_only' => 'boolean',
        'exclude_sale_items' => 'boolean',
        'stack_with_reward_points' => 'boolean',
        'max_uses_total' => 'integer',
        'max_uses_per_customer' => 'integer',
        'times_used' => 'integer',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'coupon_categories', 'coupon_id', 'category_id');
    }

    public function variations(): BelongsToMany
    {
        return $this->belongsToMany(Variation::class, 'coupon_products', 'coupon_id', 'variation_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function supportsChannel(string $channel): bool
    {
        if ($this->channel === self::CHANNEL_BOTH) {
            return true;
        }

        return $this->channel === $channel;
    }

    public function displayLabel(): string
    {
        return match ($this->type) {
            self::TYPE_PERCENT_ORDER => rtrim(rtrim(number_format((float) $this->discount_amount, 2, '.', ''), '0'), '.').'% off',
            self::TYPE_FIXED_ORDER => 'Fixed discount',
            self::TYPE_FREE_SHIPPING => 'Free shipping',
            default => $this->name,
        };
    }
}
