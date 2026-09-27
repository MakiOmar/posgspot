<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupScheduleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Backup using the scope on the POS Backup page. Older archives are kept (no cleanup).
 */
class RunAutomaticBackup extends Command
{
    protected $signature = 'backup:auto
        {--force : Run even when automatic backups are disabled (used by "Backup now")}';

    protected $description = 'Run a backup with the Backup page settings (scope); older archives are kept';

    public function handle(BackupScheduleService $schedule): int
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

        return self::SUCCESS;
    }

    private function reportFailure(BackupScheduleService $schedule, string $output): int
    {
        Log::error('Automatic backup failed', ['output' => $output]);
        $schedule->recordRun(false, $this->lastLine($output));
        $this->error('Backup failed: '.$this->lastLine($output));

        return self::FAILURE;
    }

    private function lastLine(string $output): string
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $output))));

        return $lines === [] ? '' : end($lines);
    }
}
