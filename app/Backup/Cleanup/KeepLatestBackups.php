<?php

namespace App\Backup\Cleanup;

use App\Services\Backup\BackupScheduleService;
use Spatie\Backup\BackupDestination\BackupCollection;
use Spatie\Backup\Tasks\Cleanup\CleanupStrategy;

/**
 * Keep the newest N archives, N = "Keep backups" on the POS Backup page.
 */
class KeepLatestBackups extends CleanupStrategy
{
    public function deleteOldBackups(BackupCollection $backups)
    {
        $keep = app(BackupScheduleService::class)->settings()['keep_count'];

        $backups->sortByDesc(fn ($backup) => $backup->date())
            ->slice($keep)
            ->each(fn ($backup) => $backup->delete());
    }
}
