<?php

namespace App\Http\Controllers;

use App\Http\Requests\Backup\UpdateBackupSettingsRequest;
use App\Services\Backup\BackupScheduleService;
use App\Utils\Util;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackUpController extends Controller
{
    public function __construct(
        protected Util $commonUtil,
        protected BackupScheduleService $backups
    ) {}

    /**
     * Backup archives + automatic schedule settings.
     */
    public function index()
    {
        $this->authorizeBackup();

        return view('backup.index', [
            'backups' => $this->backups->listBackups(),
            'settings' => $this->backups->settings(),
            'intervals' => array_keys(BackupScheduleService::INTERVALS),
            'scopes' => BackupScheduleService::SCOPES,
            'last_run' => $this->backups->lastRun(),
            'next_run' => $this->backups->nextRunAt(),
            'cron_job_command' => $this->commonUtil->getCronJobCommand(),
        ]);
    }

    /**
     * Run a backup now (same scope + retention as the automatic one).
     */
    public function store()
    {
        $this->authorizeBackup();

        $notAllowed = $this->commonUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        try {
            @set_time_limit(0);
            $exitCode = Artisan::call('backup:auto', ['--force' => true]);
            $output = $exitCode === 0
                ? ['success' => 1, 'msg' => __('backup.created')]
                : ['success' => 0, 'msg' => __('backup.failed', ['reason' => $this->backups->lastRun()['message']])];
        } catch (Throwable $e) {
            Log::error('Manual backup failed', ['exception' => $e]);
            $output = ['success' => 0, 'msg' => __('messages.something_went_wrong')];
        }

        return redirect()->route('backup.index')->with('status', $output);
    }

    public function updateSettings(UpdateBackupSettingsRequest $request)
    {
        $this->backups->saveSettings($request->settings());

        return redirect()->route('backup.index')
            ->with('status', ['success' => 1, 'msg' => __('backup.settings_saved')]);
    }

    public function download(string $file_name)
    {
        $this->authorizeBackup();

        if (config('app.env') == 'demo') {
            return back()->with('status', ['success' => 0, 'msg' => 'Feature disabled in demo!!']);
        }

        $path = $this->backups->pathFor($file_name);
        abort_if($path === null, 404, "The backup file doesn't exist.");

        return $this->backups->disk()->download($path, $file_name);
    }

    public function destroy(string $file_name)
    {
        $this->authorizeBackup();

        if (config('app.env') == 'demo') {
            return back()->with('status', ['success' => 0, 'msg' => 'Feature disabled in demo!!']);
        }

        $path = $this->backups->pathFor($file_name);
        abort_if($path === null, 404, "The backup file doesn't exist.");

        $this->backups->disk()->delete($path);

        return redirect()->route('backup.index')
            ->with('status', ['success' => 1, 'msg' => __('backup.deleted')]);
    }

    private function authorizeBackup(): void
    {
        if (! auth()->user()->can('backup')) {
            abort(403, 'Unauthorized action.');
        }
    }
}
