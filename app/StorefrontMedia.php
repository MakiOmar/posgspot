<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Per-business reusable storefront media library asset (deduped by checksum).
 */
class StorefrontMedia extends Model
{
    use SoftDeletes;

    protected $table = 'storefront_media';

    protected $guarded = ['id'];

    protected $appends = ['url'];

    protected $casts = [
        'bytes' => 'integer',
        'uploaded_by' => 'integer',
        'business_id' => 'integer',
    ];

    public function getUrlAttribute(): string
    {
        $path = ltrim(str_replace('\\', '/', (string) $this->path), '/');

        return asset('uploads/'.$path);
    }

    public function absolutePath(): string
    {
        $path = ltrim(str_replace('\\', '/', (string) $this->path), '/');

        return public_path('uploads/'.$path);
    }
}
