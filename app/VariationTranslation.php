<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariationTranslation extends Model
{
    protected $guarded = ['id'];

    public function variation(): BelongsTo
    {
        return $this->belongsTo(Variation::class);
    }
}
