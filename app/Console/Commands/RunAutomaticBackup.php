<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupScheduleService;
use App\Services\Backup\GoogleDriveBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Backup using the scope on the POS Backup page. Older archives are kept (no cleanup).
 * When Google Drive is enabled, the new archive is also copied there (Drive keeps the last N days).
 */
class RunAutomaticBackup extends Command
{
    protected $signature = 'backup:auto
        {--force : Run even when automatic backups are disabled (used by "Backup now")}';

    protected $description = 'Run a backup with the Backup page settings (scope); older archives are kept';

    public function handle(BackupScheduleService $schedule, GoogleDriveBackupService $drive): int
    {
        $settings = $schedule->settings();
        if (! $settings['enabled'] && ! $this->option('force')) {
            $this->info('Automatic backups are disabled; nothing to do.');

            return self::SUCCESS;
        }

        try {
            $options = [];
            if ($settings['scope'] === 'db') {
                $options['--only-db'] = true;
            }

            $exitCode = Artisan::call('backup:run', $options);
            $output = Artisan::output();
            if ($exitCode !== self::SUCCESS) {
                return $this->reportFailure($schedule, $output);
            }
        } catch (Throwable $e) {
            return $this->reportFailure($schedule, $e->getMessage());
        }

        $schedule->recordRun(true, $this->lastLine($output));
        $this->info('Backup completed.');
        $this->copyToGoogleDrive($schedule, $drive);

        return self::SUCCESS;
    }

    /**
     * Off-site copy: a Drive failure is recorded separately and never fails the local backup.
     */
    private function copyToGoogleDrive(BackupScheduleService $schedule, GoogleDriveBackupService $drive): void
    {
        if (! $drive->enabled()) {
            return;
        }

        try {
            $result = $drive->syncLatest($schedule->diskName(), $schedule->directory());
        } catch (Throwable $e) {
            $reason = GoogleDriveBackupService::describe($e);
            Log::error('Google Drive backup copy failed', ['exception' => $e]);
            $schedule->recordDriveRun(false, $reason);
            $this->warn('Google Drive copy failed: '.$reason);

            return;
        }

        $message = "Uploaded {$result['file']}; removed {$result['pruned']} old Drive copies.";
        $schedule->recordDriveRun(true, $message);
        $this->info($message);
    }

    private function reportFailure(BackupScheduleService $schedule, string $output): int
    {
        $reason = self::failureReason($output);
        Log::error('Automatic backup failed', ['output' => $output]);
        $schedule->recordRun(false, $reason);
        $this->error('Backup failed: '.$reason);

        return self::FAILURE;
    }

    /**
     * Spatie ends failure output with a lone "." after the process dump, so the last line says
     * nothing; prefer "Backup failed because: …" plus the first line of its "Error Output".
     */
    public static function failureReason(string $output): string
    {
        $lines = self::lines($output);
        $reason = '';
        $errorOutput = '';
        foreach ($lines as $index => $line) {
            if ($reason === '' && str_starts_with($line, 'Backup failed because:')) {
                $reason = rtrim(trim(substr($line, strlen('Backup failed because:'))), '.');
            }
            if ($errorOutput === '' && $line === 'Error Output') {
                $next = $lines[$index + 2] ?? '';
                $errorOutput = in_array($next, ['.', '<no output>'], true) ? '' : $next;
            }
        }

        if ($reason === '') {
            return $lines === [] ? '' : end($lines);
        }

        return $errorOutput === '' ? $reason : $reason.' — '.$errorOutput;
    }

    private function lastLine(string $output): string
    {
        $lines = self::lines($output);

        return $lines === [] ? '' : end($lines);
    }

    /**
     * @return list<string>
     */
    private static function lines(string $output): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", $output)), fn (string $line) => $line !== ''));
    }
}
