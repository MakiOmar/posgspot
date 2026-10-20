<?php

namespace App\Console\Commands;

use App\Services\Backup\GoogleDriveBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckGoogleDriveBackupCommand extends Command
{
    protected $signature = 'backup:drive-check';

    protected $description = 'Verify the Google Drive backup credentials and folder (write, read and delete a probe file)';

    public function handle(GoogleDriveBackupService $drive): int
    {
        if (! $drive->enabled()) {
            $this->error('Google Drive backups are disabled. Set BACKUP_GOOGLE_DRIVE_ENABLED=true and the GOOGLE_DRIVE_* keys.');

            return self::FAILURE;
        }

        try {
            $drive->check();
        } catch (Throwable $e) {
            Log::error('Google Drive backup check failed', ['exception' => $e]);
            $this->error('Google Drive check failed: '.GoogleDriveBackupService::describe($e));

            return self::FAILURE;
        }

        $this->info('Google Drive is reachable and writable. Copies are kept for '.$drive->keepDays().' day(s).');

        return self::SUCCESS;
    }
}
