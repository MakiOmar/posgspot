<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperator;
use RuntimeException;
use Throwable;

/**
 * Off-site copy of spatie/laravel-backup archives on Google Drive (yaza/laravel-google-drive-storage).
 *
 * Talks to the Flysystem operator directly: the package's Laravel adapter is built with throw=false,
 * so failed Drive calls would otherwise return false without the reason.
 */
class GoogleDriveBackupService
{
    private const PROBE_FILE = '.backup-drive-check.txt';

    public function enabled(): bool
    {
        return (bool) config('backup.google_drive.enabled', false);
    }

    public function keepDays(): int
    {
        return max(1, (int) config('backup.google_drive.keep_days', 14));
    }

    /**
     * Upload the newest local archive, then drop Drive copies older than keepDays().
     *
     * @return array{file: string, pruned: int}
     */
    public function syncLatest(string $sourceDisk, string $directory): array
    {
        $file = $this->uploadLatest($sourceDisk, $directory);

        return ['file' => $file, 'pruned' => $this->prune($directory)];
    }

    /**
     * Stream the newest .zip in $sourceDisk/$directory to the same path on Drive.
     */
    public function uploadLatest(string $sourceDisk, string $directory): string
    {
        $source = Storage::disk($sourceDisk);
        $latest = collect($source->files($directory))
            ->filter(fn (string $path) => str_ends_with($path, '.zip'))
            ->sortByDesc(fn (string $path) => $source->lastModified($path))
            ->first();

        if ($latest === null) {
            throw new RuntimeException("No backup archive found in \"{$directory}\".");
        }

        $stream = $source->readStream($latest);
        if (! is_resource($stream)) {
            throw new RuntimeException("Could not read \"{$latest}\".");
        }

        try {
            $this->drive()->writeStream($latest, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return basename($latest);
    }

    /**
     * Delete Drive archives older than keepDays(); the newest archive is always kept.
     */
    public function prune(string $directory): int
    {
        $drive = $this->drive();
        $cutoff = now()->subDays($this->keepDays())->getTimestamp();

        $archives = collect($drive->listContents($directory, false))
            ->filter(fn ($item) => $item instanceof FileAttributes && str_ends_with($item->path(), '.zip'))
            ->map(fn (FileAttributes $file) => [
                'path' => $file->path(),
                'modified' => $file->lastModified() ?? $drive->lastModified($file->path()),
            ])
            ->sortByDesc('modified')
            ->values();

        $stale = $archives->slice(1)->filter(fn (array $archive) => $archive['modified'] < $cutoff);
        foreach ($stale as $archive) {
            $drive->delete($archive['path']);
        }

        if ($stale->isNotEmpty()) {
            Log::info('Pruned Google Drive backups', ['directory' => $directory, 'deleted' => $stale->count()]);
        }

        return $stale->count();
    }

    /**
     * Write, read back and delete a small probe file; throws with the Drive error on failure.
     */
    public function check(): void
    {
        $drive = $this->drive();
        $token = bin2hex(random_bytes(8));

        $drive->write(self::PROBE_FILE, $token);
        try {
            if ($drive->read(self::PROBE_FILE) !== $token) {
                throw new RuntimeException('The probe file read back from Google Drive did not match.');
            }
        } finally {
            $drive->delete(self::PROBE_FILE);
        }
    }

    /**
     * Short, operator-friendly reason: Google API errors carry a JSON body with error.message.
     */
    public static function describe(Throwable $e): string
    {
        $message = trim($e->getMessage());
        $decoded = json_decode($message, true);
        if (is_array($decoded)) {
            $message = (string) ($decoded['error']['message'] ?? $decoded['error_description'] ?? $decoded['error'] ?? $message);
        }

        return mb_substr($message !== '' ? $message : class_basename($e), 0, 500);
    }

    private function drive(): FilesystemOperator
    {
        return Storage::disk((string) config('backup.google_drive.disk', 'google'))->getDriver();
    }
}
