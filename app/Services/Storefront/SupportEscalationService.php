<?php

namespace App\Services\Storefront;

use App\Contact;
use App\StorefrontSupport\SupportConversation;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Entities\Escalation;
use Modules\Crm\Entities\EscalationSource;
use Modules\Crm\Entities\EscalationStatusLog;

/**
 * Create CRM escalations from storefront AI chat (Phase C). Auth contacts only.
 */
class SupportEscalationService
{
    public function isConfigured(int $businessId): bool
    {
        return $this->employeeId() > 0
            && $this->locationId() > 0
            && $this->createdByUserId() > 0
            && $this->resolveSourceId($businessId) !== null;
    }

    /**
     * @throws \RuntimeException
     */
    public function escalateFromConversation(
        SupportConversation $conversation,
        Contact $contact,
        ?string $customerNote = null,
        ?int $transactionId = null
    ): Escalation {
        $businessId = (int) $conversation->business_id;
        if (! $this->isConfigured($businessId)) {
            throw new \RuntimeException('Support escalation is not configured.');
        }

        $sourceId = $this->resolveSourceId($businessId);
        if ($sourceId === null) {
            throw new \RuntimeException('Escalation source is missing.');
        }

        $transcript = $conversation->messages()
            ->orderBy('id')
            ->limit(40)
            ->get()
            ->map(fn ($m) => strtoupper((string) $m->role).': '.$this->truncate((string) $m->content, 500))
            ->implode("\n");

        $transcript = $this->truncate($transcript, 4000);

        $description = trim(
            "Storefront AI chat escalation\n".
            'Conversation: '.$conversation->uuid."\n".
            ($customerNote ? "Customer note: {$customerNote}\n" : '').
            "---\n".$transcript
        );

        $employeeId = $this->employeeId();
        $locationId = $this->locationId();
        $createdBy = $this->createdByUserId();
        $phone = substr(trim((string) ($contact->mobile ?? '')), 0, 20) ?: null;

        return DB::transaction(function () use (
            $businessId,
            $conversation,
            $contact,
            $sourceId,
            $description,
            $employeeId,
            $locationId,
            $createdBy,
            $phone,
            $transactionId
        ) {
            $reference = $this->nextReference($businessId);

            $escalation = Escalation::create([
                'business_id' => $businessId,
                'reference_no' => $reference,
                'employee_id' => $employeeId,
                'contact_id' => $contact->id,
                'phone' => $phone,
                'escalated_at' => now(),
                'description' => $description,
                'source_id' => $sourceId,
                'location_id' => $locationId,
                'transaction_id' => $transactionId,
                'status' => 'open',
                'created_by' => $createdBy,
            ]);

            EscalationStatusLog::create([
                'escalation_id' => $escalation->id,
                'user_id' => $createdBy,
                'from_status' => null,
                'to_status' => 'open',
                'note' => 'Created from storefront AI support chat',
            ]);

            $conversation->escalation_id = $escalation->id;
            $conversation->status = 'closed';
            $conversation->save();

            return $escalation;
        });
    }

    public function ensureSource(int $businessId): ?EscalationSource
    {
        $name = (string) config('storefront.support_chat.escalation_source_name', 'Storefront AI Chat');
        $createdBy = $this->createdByUserId();
        if ($createdBy < 1) {
            return EscalationSource::query()
                ->where('business_id', $businessId)
                ->where('name', $name)
                ->first();
        }

        return EscalationSource::query()->firstOrCreate(
            [
                'business_id' => $businessId,
                'name' => $name,
            ],
            [
                'is_active' => 1,
                'created_by' => $createdBy,
            ]
        );
    }

    private function resolveSourceId(int $businessId): ?int
    {
        $source = $this->ensureSource($businessId);

        return $source?->id;
    }

    private function employeeId(): int
    {
        return (int) config('storefront.support_chat.escalation_employee_id', 0);
    }

    private function locationId(): int
    {
        return (int) config('storefront.support_chat.escalation_location_id', 0);
    }

    private function createdByUserId(): int
    {
        return (int) config('storefront.support_chat.escalation_created_by', 0);
    }

    private function nextReference(int $businessId): string
    {
        $count = Escalation::withTrashed()->where('business_id', $businessId)->count() + 1;

        return 'SF-AI-'.str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }

    private function truncate(string $value, int $max): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max - 1).'…';
    }
}
