<?php

namespace App\Http\Requests\Backup;

use App\Services\Backup\BackupScheduleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBackupSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('backup');
    }

    public function rules(): array
    {
        return [
            'enabled' => ['nullable', 'boolean'],
            'interval' => ['required', Rule::in(array_keys(BackupScheduleService::INTERVALS))],
            'scope' => ['required', Rule::in(BackupScheduleService::SCOPES)],
            'keep_count' => [
                'required',
                'integer',
                'min:'.BackupScheduleService::KEEP_COUNT_MIN,
                'max:'.BackupScheduleService::KEEP_COUNT_MAX,
            ],
        ];
    }

    /**
     * @return array{enabled: bool, interval: string, scope: string, keep_count: int}
     */
    public function settings(): array
    {
        return [
            'enabled' => $this->boolean('enabled'),
            'interval' => (string) $this->validated('interval'),
            'scope' => (string) $this->validated('scope'),
            'keep_count' => (int) $this->validated('keep_count'),
        ];
    }
}
