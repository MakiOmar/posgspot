<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OAuth identity linked to a storefront Contact (Google / Facebook).
 */
class StorefrontSocialIdentity extends Model
{
    protected $table = 'storefront_social_identities';

    protected $fillable = [
        'business_id',
        'contact_id',
        'provider',
        'provider_user_id',
        'email',
        'avatar_url',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
