<?php

namespace App\StorefrontSupport;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportConversation extends Model
{
    protected $table = 'storefront_support_conversations';

    protected $fillable = [
        'business_id',
        'uuid',
        'contact_id',
        'guest_token',
        'locale',
        'status',
        'title',
        'escalation_id',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'conversation_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(\App\Contact::class, 'contact_id');
    }
}
