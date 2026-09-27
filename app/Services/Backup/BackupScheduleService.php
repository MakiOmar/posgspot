<?php

namespace App\Services\Backup;

use App\System;
use Cron\CronExpression;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Automatic backup schedule (system table) + access to backup archives on the backup disk.
 */
class BackupScheduleService
{
    public const INTERVALS = [
        'every_30_minutes' => '*/30 * * * *',
        'hourly' => '0 * * * *',
        'every_2_hours' => '0 */2 * * *',
        'every_6_hours' => '0 */6 * * *',
        'every_12_hours' => '0 */12 * * *',
        'daily' => '30 1 * * *',
        'weekly' => '30 1 * * 0',
    ];

    public const SCOPES = ['full', 'db'];

    public const DEFAULTS = [
        'enabled' => true,
        'interval' => 'hourly',
        'scope' => 'full',
    ];

    private const KEY_PREFIX = 'backup_auto_';

    /**
     * @return array{enabled: bool, interval: string, scope: string}
     */
    public function settings(): array
    {
        $stored = System::getProperties($this->keys(['enabled', 'interval', 'scope']), true);
        $get = fn (string $name) => $stored[self::KEY_PREFIX.$name] ?? null;

        $interval = (string) $get('interval');
        $scope = (string) $get('scope');

        return [
            'enabled' => $get('enabled') === null ? self::DEFAULTS['enabled'] : $get('enabled') === '1',
            'interval' => array_key_exists($interval, self::INTERVALS) ? $interval : self::DEFAULTS['interval'],
            'scope' => in_array($scope, self::SCOPES, true) ? $scope : self::DEFAULTS['scope'],
        ];
    }

    /**
     * @param  array{enabled: bool, interval: string, scope: string}  $settings
     */
    public function saveSettings(array $settings): void
    {
        System::addProperty(self::KEY_PREFIX.'enabled', $settings['enabled'] ? '1' : '0');
        System::addProperty(self::KEY_PREFIX.'interval', $settings['interval']);
        System::addProperty(self::KEY_PREFIX.'scope', $settings['scope']);
    }

    /**
     * Free bytes on the volume holding backups; null for remote disks (S3, Dropbox…).
     */
    public function freeDiskSpace(): ?int
    {
        $disk = $this->disk();
        if (! method_exists($disk, 'path')) {
            return null;
        }
        $root = $disk->path('');
        $free = is_dir($root) ? @disk_free_space($root) : false;

        return $free === false ? null : (int) $free;
    }

    public function cronExpression(?string $interval = null): string
    {
        return self::INTERVALS[$interval ?? $this->settings()['interval']] ?? self::INTERVALS[self::DEFAULTS['interval']];
    }

    public function nextRunAt(): ?Carbon
    {
        if (! $this->settings()['enabled']) {
            return null;
        }

        $next = (new CronExpression($this->cronExpression()))
            ->getNextRunDate(now(), 0, false, config('app.timezone'));

        return Carbon::instance($next);
    }

    public function recordRun(bool $success, string $message): void
    {
        System::addProperty(self::KEY_PREFIX.'last_run_at', now()->toIso8601String());
        System::addProperty(self::KEY_PREFIX.'last_status', $success ? 'success' : 'failed');
        System::addProperty(self::KEY_PREFIX.'last_message', mb_substr(trim($message), 0, 1000));
    }

    /**
     * @return array{at: ?Carbon, status: ?string, message: ?string}
     */
    public function lastRun(): array
    {
        $stored = System::getProperties($this->keys(['last_run_at', 'last_status', 'last_message']), true);
        $at = $stored[self::KEY_PREFIX.'last_run_at'] ?? null;

        return [
            'at' => $at ? Carbon::parse($at) : null,
            'status' => $stored[self::KEY_PREFIX.'last_status'] ?? null,
            'message' => $stored[self::KEY_PREFIX.'last_message'] ?? null,
        ];
    }

    public function disk(): Filesystem
    {
        return Storage::disk(config('backup.backup.destination.disks')[0]);
    }

    /**
     * Newest first.
     *
     * @return list<array{file_name: string, file_size: int, last_modified: int}>
     */
    public function listBackups(): array
    {
        $disk = $this->disk();
        $backups = [];
        foreach ($disk->files($this->directory()) as $path) {
            if (str_ends_with($path, '.zip')) {
                $backups[] = [
                    'file_name' => basename($path),
                    'file_size' => $disk->size($path),
                    'last_modified' => $disk->lastModified($path),
                ];
            }
        }
        usort($backups, fn (array $a, array $b) => $b['last_modified'] <=> $a['last_modified']);

        return $backups;
    }

    /**
     * Disk path for a listed archive, or null for unknown / unsafe names.
     */
    public function pathFor(string $fileName): ?string
    {
        if ($fileName !== basename($fileName) || ! str_ends_with($fileName, '.zip')) {
            return null;
        }
        $path = $this->directory().'/'.$fileName;

        return $this->disk()->exists($path) ? $path : null;
    }

    private function directory(): string
    {
        return str_replace('\\', '/', (string) config('backup.backup.name'));
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function keys(array $names): array
    {
        return array_map(fn (string $name) => self::KEY_PREFIX.$name, $names);
    }
}
