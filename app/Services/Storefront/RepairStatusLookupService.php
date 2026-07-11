<?php

namespace App\Services\Storefront;

use App\Support\StorefrontLocale;
use App\Utils\ModuleUtil;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Modules\Repair\Entities\JobSheet;
use Spatie\Activitylog\Models\Activity;

/**
 * Public repair job-sheet lookup for the Qwik storefront.
 */
class RepairStatusLookupService
{
    public function __construct(private ModuleUtil $moduleUtil)
    {
    }

    public function isAvailable(int $businessId): bool
    {
        if (! class_exists(JobSheet::class) || ! Schema::hasTable('repair_job_sheets')) {
            return false;
        }

        try {
            return (bool) $this->moduleUtil->hasThePermissionInSubscription($businessId, 'repair_module');
        } catch (\Throwable $e) {
            // Fall back to table presence when subscription check is unavailable.
            return true;
        }
    }

    public function lookupByMobileEnabled(): bool
    {
        return (bool) config('repair.enable_repair_check_using_mobile_num', false);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lookup(
        int $businessId,
        string $searchType,
        string $searchNumber,
        ?string $serialNo,
        string $locale = StorefrontLocale::DEFAULT
    ): array {
        if (! $this->isAvailable($businessId)) {
            return [];
        }

        $previousLocale = app()->getLocale();
        app()->setLocale($locale === 'ar' ? 'ar' : 'en');

        try {
            $query = JobSheet::query()
                ->where('repair_job_sheets.business_id', $businessId)
                ->leftJoin('transactions', 'transactions.repair_job_sheet_id', '=', 'repair_job_sheets.id')
                ->join('contacts', 'repair_job_sheets.contact_id', '=', 'contacts.id')
                ->leftJoin('repair_statuses AS rs', 'repair_job_sheets.status_id', '=', 'rs.id')
                ->leftJoin('brands AS b', 'repair_job_sheets.brand_id', '=', 'b.id')
                ->leftJoin('repair_device_models as rdm', 'rdm.id', '=', 'repair_job_sheets.device_model_id')
                ->leftJoin('categories as device', 'device.id', '=', 'repair_job_sheets.device_id');

            if ($searchType === 'job_sheet_no') {
                $query->where('repair_job_sheets.job_sheet_no', $searchNumber);
            } elseif ($searchType === 'invoice_no') {
                $query->where('transactions.invoice_no', $searchNumber);
            } elseif ($searchType === 'mobile_num') {
                if (! $this->lookupByMobileEnabled()) {
                    return [];
                }
                $digits = preg_replace('/\D+/', '', $searchNumber) ?? '';
                $query->where(function ($q) use ($searchNumber, $digits) {
                    $q->where('contacts.mobile', $searchNumber);
                    if ($digits !== '') {
                        $q->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(contacts.mobile, '+', ''), '-', ''), ' ', ''), '(', ''), ')', '') = ?",
                            [$digits]
                        );
                    }
                });
            } else {
                return [];
            }

            if (! empty($serialNo)) {
                $query->where('repair_job_sheets.serial_no', $serialNo);
            }

            $rows = $query->select(
                'repair_job_sheets.*',
                'rs.name as repair_status',
                'rs.color as repair_status_color',
                'rdm.name as repair_model',
                'device.name as repair_device',
                'b.name as manufacturer'
            )
                ->groupBy('repair_job_sheets.id')
                ->orderByDesc('repair_job_sheets.id')
                ->limit(20)
                ->get();

            $results = [];
            foreach ($rows as $row) {
                $results[] = $this->formatJobSheet($row);
            }

            return $results;
        } finally {
            app()->setLocale($previousLocale);
        }
    }

    /**
     * @param  JobSheet  $row
     * @return array<string, mixed>
     */
    private function formatJobSheet($row): array
    {
        $activities = Activity::forSubject($row)
            ->with(['causer'])
            ->latest()
            ->get();

        $activityPayload = [];
        foreach ($activities as $activity) {
            if ($activity->description === 'is_sent_notification') {
                continue;
            }

            $action = $activity->description === 'status_changed'
                ? __('repair::lang.status_changed_to', [
                    'status' => $activity->getExtraProperty('updated_status'),
                ])
                : __('lang_v1.'.$activity->description);

            $note = trim((string) ($activity->getExtraProperty('update_note') ?? ''));
            $completedFrom = $activity->getExtraProperty('completed_on_from');
            $completedTo = $activity->getExtraProperty('completed_on_to');
            if (! empty($completedFrom) && ! empty($completedTo)) {
                $note = trim($note.' '.__('repair::lang.completed_on_changed')
                    .' '.__('account.from').': '.$completedFrom
                    .' '.__('account.to').': '.$completedTo);
            }

            $by = 'Staff';
            if ($activity->causer) {
                $by = trim((string) ($activity->causer->user_full_name ?? $activity->causer->first_name ?? 'Staff'));
            }

            $activityPayload[] = [
                'date' => optional($activity->created_at)->toIso8601String(),
                'date_label' => optional($activity->created_at)->toDayDateTimeString(),
                'action' => $action,
                'by' => $by !== '' ? $by : 'Staff',
                'note' => $note !== '' ? $note : null,
            ];
        }

        $delivery = $row->delivery_date ? Carbon::parse($row->delivery_date) : null;

        return [
            'job_sheet_no' => $row->job_sheet_no,
            'brand' => $row->manufacturer,
            'device' => $row->repair_device,
            'model' => $row->repair_model,
            'serial_no' => $row->serial_no,
            'status' => $row->repair_status,
            'status_color' => $row->repair_status_color,
            'due_date' => $delivery?->toIso8601String(),
            'due_date_label' => $delivery?->toDayDateTimeString(),
            'activities' => $activityPayload,
        ];
    }
}
