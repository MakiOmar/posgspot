<?php

namespace App\Backup\Cleanup;

use Spatie\Backup\BackupDestination\BackupCollection;
use Spatie\Backup\Tasks\Cleanup\CleanupStrategy;

/**
 * Backups are never deleted automatically (also when backup:clean is run by hand);
 * they are removed only from the POS Backup page.
 */
class KeepAllBackups extends CleanupStrategy
{
    public function deleteOldBackups(BackupCollection $backups)
    {
    }
}
