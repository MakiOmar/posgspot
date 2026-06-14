<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class EscalationStatusLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'crm_escalation_status_logs';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Parent escalation.
     */
    public function escalation()
    {
        return $this->belongsTo(Escalation::class, 'escalation_id');
    }

    /**
     * User who changed the status.
     */
    public function user()
    {
        return $this->belongsTo(\App\User::class, 'user_id');
    }
}
