<?php

namespace Modules\Crm\Utils;

use Modules\Crm\Entities\Escalation;
use Modules\Crm\Entities\EscalationSource;
use Modules\Crm\Entities\EscalationStatusLog;

class EscalationUtil extends CrmUtil
{
    /**
     * Default escalation source channel names.
     */
    protected $default_sources = [
        'Instagram',
        'Facebook',
        'WhatsApp',
        'Phone',
        'Email',
        'In-store',
        'Website',
    ];

    /**
     * Generate a unique escalation reference number.
     */
    public function generateReferenceNo($business_id)
    {
        $ref_count = $this->setAndGetReferenceCount('crm_escalation', $business_id);

        return $this->generateReferenceNumber('crm_escalation', $ref_count, $business_id, 'ESC-');
    }

    /**
     * Seed default escalation sources for a business if none exist.
     */
    public function seedDefaultSources($business_id, $created_by = null)
    {
        $exists = EscalationSource::where('business_id', $business_id)->exists();

        if ($exists) {
            return;
        }

        $created_by = $created_by ?? auth()->user()->id ?? 1;

        foreach ($this->default_sources as $name) {
            EscalationSource::create([
                'business_id' => $business_id,
                'name' => $name,
                'is_active' => 1,
                'created_by' => $created_by,
            ]);
        }
    }

    /**
     * Change escalation status and log the transition.
     */
    public function changeStatus(Escalation $escalation, $to_status, $note = null)
    {
        $from_status = $escalation->status;

        if ($from_status === $to_status) {
            return $escalation;
        }

        $escalation->status = $to_status;
        $escalation->updated_by = auth()->user()->id;
        $escalation->save();

        EscalationStatusLog::create([
            'escalation_id' => $escalation->id,
            'user_id' => auth()->user()->id,
            'from_status' => $from_status,
            'to_status' => $to_status,
            'note' => $note,
        ]);

        return $escalation;
    }

    /**
     * Suggest status based on callback datetime.
     */
    public function suggestStatusFromCallback($callback_at, $current_status = 'open')
    {
        if (! empty($callback_at) && ! in_array($current_status, ['closed', 'cancelled', 'resolved'])) {
            return 'callback_scheduled';
        }

        return $current_status;
    }

    /**
     * Check if user can update the given escalation.
     */
    public function canUserUpdate(Escalation $escalation, $user = null)
    {
        $user = $user ?? auth()->user();

        if ($user->can('superadmin') || $user->can('crm.escalation.update_all')) {
            return true;
        }

        if ($user->can('crm.escalation.update_own')) {
            return $escalation->employee_id == $user->id || $escalation->created_by == $user->id;
        }

        return false;
    }

    /**
     * Check if user can view the given escalation.
     */
    public function canUserView(Escalation $escalation, $user = null)
    {
        $user = $user ?? auth()->user();

        if ($user->can('superadmin') || $user->can('crm.escalation.view_all')) {
            return true;
        }

        if ($user->can('crm.escalation.view_own')) {
            return in_array($user->id, array_filter([
                $escalation->employee_id,
                $escalation->observer_id,
                $escalation->auditor_id,
                $escalation->created_by,
            ]));
        }

        return false;
    }
}
