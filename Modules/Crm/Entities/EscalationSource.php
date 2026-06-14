<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class EscalationSource extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'crm_escalation_sources';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Scope active sources only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Dropdown for select fields.
     */
    public static function forDropdown($business_id, $prepend_none = true)
    {
        $sources = self::where('business_id', $business_id)
            ->active()
            ->orderBy('name')
            ->pluck('name', 'id');

        if ($prepend_none) {
            $sources = $sources->prepend(__('messages.please_select'), '');
        }

        return $sources;
    }
}
