<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StorefrontShippingMethod extends Model
{
    protected $table = 'storefront_shipping_methods';

    protected $guarded = ['id'];

    protected $casts = [
        'title_i18n' => 'array',
        'settings' => 'array',
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    public const TYPE_FLAT_RATE = 'flat_rate';

    public const TYPE_FREE_SHIPPING = 'free_shipping';

    public const TYPE_LOCAL_PICKUP = 'local_pickup';

    public function zone()
    {
        return $this->belongsTo(StorefrontShippingZone::class, 'zone_id');
    }

    public function localizedTitle(string $locale = 'en'): string
    {
        $i18n = $this->title_i18n ?? [];
        if (! empty($i18n[$locale])) {
            return (string) $i18n[$locale];
        }
        if (! empty($i18n['en'])) {
            return (string) $i18n['en'];
        }

        return (string) $this->title;
    }
}
