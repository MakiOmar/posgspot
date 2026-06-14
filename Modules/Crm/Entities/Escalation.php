<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Escalation extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'crm_escalations';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'escalated_at' => 'datetime',
        'callback_at' => 'datetime',
    ];

    /**
     * Employee who logged the escalation.
     */
    public function employee()
    {
        return $this->belongsTo(\App\User::class, 'employee_id');
    }

    /**
     * Customer contact.
     */
    public function contact()
    {
        return $this->belongsTo(\App\Contact::class, 'contact_id');
    }

    /**
     * Communication channel source.
     */
    public function source()
    {
        return $this->belongsTo(EscalationSource::class, 'source_id');
    }

    /**
     * Business location.
     */
    public function location()
    {
        return $this->belongsTo(\App\BusinessLocation::class, 'location_id');
    }

    /**
     * Related sale transaction.
     */
    public function transaction()
    {
        return $this->belongsTo(\App\Transaction::class, 'transaction_id');
    }

    /**
     * Observer user.
     */
    public function observer()
    {
        return $this->belongsTo(\App\User::class, 'observer_id');
    }

    /**
     * Auditor user.
     */
    public function auditor()
    {
        return $this->belongsTo(\App\User::class, 'auditor_id');
    }

    /**
     * User who created the record.
     */
    public function createdBy()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    /**
     * Status change history.
     */
    public function statusLogs()
    {
        return $this->hasMany(EscalationStatusLog::class, 'escalation_id');
    }

    /**
     * Return status dropdown options.
     */
    public static function statusDropdown($add_none = false)
    {
        $statuses = [
            'open' => __('crm::lang.escalation_status_open'),
            'in_review' => __('crm::lang.escalation_status_in_review'),
            'callback_scheduled' => __('crm::lang.escalation_status_callback_scheduled'),
            'resolved' => __('crm::lang.escalation_status_resolved'),
            'closed' => __('crm::lang.escalation_status_closed'),
            'cancelled' => __('crm::lang.escalation_status_cancelled'),
        ];

        if ($add_none) {
            $statuses = ['' => __('messages.all')] + $statuses;
        }

        return $statuses;
    }

    /**
     * Check if escalation is closed.
     */
    public function isClosed()
    {
        return in_array($this->status, ['closed', 'cancelled']);
    }
}
