<?php

namespace Tests\Feature;

use App\Services\Backup\BackupScheduleService;
use App\Services\Backup\GoogleDriveBackupService;
use App\System;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Off-site Google Drive copy of POS backups (Drive is faked; Google is never called).
 */
class GoogleDriveBackupTest extends TestCase
{
    use DatabaseTransactions;

    private string $dir;

    private const DRIVE_ENV = [
        'BACKUP_GOOGLE_DRIVE_ENABLED' => 'true',
        'GOOGLE_DRIVE_CLIENT_ID' => 'client-id',
        'GOOGLE_DRIVE_CLIENT_SECRET' => 'client-secret',
        'GOOGLE_DRIVE_REFRESH_TOKEN' => 'refresh-token',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'backup.backup.destination.disks' => ['backups'],
            'backup.google_drive' => ['enabled' => true, 'disk' => 'google', 'keep_days' => 14],
        ]);
        Storage::fake('backups');
        Storage::fake('google');
        $this->dir = (string) config('backup.backup.name');
        System::where('key', 'like', 'backup_auto_%')->delete();
    }

    private function putArchive(string $disk, string $name, int $daysAgo): void
    {
        $path = $this->dir.'/'.$name;
        Storage::disk($disk)->put($path, 'zip:'.$name);
        touch(Storage::disk($disk)->path($path), now()->subDays($daysAgo)->getTimestamp());
    }

    /**
     * @param  array<string, string>  $env
     */
    private function loadBackupConfig(array $env): array
    {
        foreach ($env as $key => $value) {
            $_SERVER[$key] = $_ENV[$key] = $value;
        }
        try {
            return require config_path('backup.php');
        } finally {
            foreach (array_keys($env) as $key) {
                unset($_SERVER[$key], $_ENV[$key]);
            }
        }
    }

    public function test_drive_is_enabled_only_with_flag_and_credentials(): void
    {
        $this->assertTrue($this->loadBackupConfig(self::DRIVE_ENV)['google_drive']['enabled']);
        $this->assertFalse($this->loadBackupConfig(['GOOGLE_DRIVE_REFRESH_TOKEN' => ''] + self::DRIVE_ENV)['google_drive']['enabled']);
        $this->assertFalse($this->loadBackupConfig(['BACKUP_GOOGLE_DRIVE_ENABLED' => 'false'] + self::DRIVE_ENV)['google_drive']['enabled']);

        $config = $this->loadBackupConfig(self::DRIVE_ENV + ['BACKUP_GOOGLE_DRIVE_KEEP_DAYS' => '0']);
        $this->assertSame(1, $config['google_drive']['keep_days']);
        $this->assertNotContains('google', $config['backup']['destination']['disks']);
    }

    public function test_sync_uploads_newest_archive_and_prunes_old_drive_copies(): void
    {
        $this->putArchive('backups', '2026-10-01-01-00-00.zip', 2);
        $this->putArchive('backups', '2026-10-03-01-00-00.zip', 0);
        $this->putArchive('google', '2026-09-01-01-00-00.zip', 30);
        $this->putArchive('google', '2026-10-01-01-00-00.zip', 2);

        $result = app(GoogleDriveBackupService::class)->syncLatest('backups', $this->dir);

        $this->assertSame(['file' => '2026-10-03-01-00-00.zip', 'pruned' => 1], $result);
        Storage::disk('google')->assertExists($this->dir.'/2026-10-03-01-00-00.zip');
        Storage::disk('google')->assertExists($this->dir.'/2026-10-01-01-00-00.zip');
        Storage::disk('google')->assertMissing($this->dir.'/2026-09-01-01-00-00.zip');
        $this->assertSame('zip:2026-10-03-01-00-00.zip', Storage::disk('google')->get($this->dir.'/2026-10-03-01-00-00.zip'));
    }

    public function test_prune_never_deletes_the_newest_drive_copy(): void
    {
        $this->putArchive('google', '2026-01-01-01-00-00.zip', 200);
        $this->putArchive('google', '2026-02-01-01-00-00.zip', 100);

        $this->assertSame(1, app(GoogleDriveBackupService::class)->prune($this->dir));
        Storage::disk('google')->assertExists($this->dir.'/2026-02-01-01-00-00.zip');
        Storage::disk('google')->assertMissing($this->dir.'/2026-01-01-01-00-00.zip');
    }

    public function test_automatic_backup_copies_archive_to_drive(): void
    {
        $this->useFilesOnlyBackupSource();

        $this->artisan('backup:auto', ['--force' => true])->assertSuccessful();

        $local = app(BackupScheduleService::class)->listBackups();
        $this->assertCount(1, $local);
        Storage::disk('google')->assertExists($this->dir.'/'.$local[0]['file_name']);
        $status = app(BackupScheduleService::class)->driveStatus();
        $this->assertSame('success', $status['status']);
        $this->assertStringContainsString($local[0]['file_name'], $status['message']);
    }

    public function test_drive_failure_keeps_local_backup_and_records_drive_status(): void
    {
        $this->useFilesOnlyBackupSource();
        $drive = Mockery::mock(GoogleDriveBackupService::class)->makePartial();
        $drive->shouldReceive('syncLatest')->once()->andThrow(new RuntimeException('{"error":"invalid_grant","error_description":"Token has been expired or revoked."}'));
        $this->app->instance(GoogleDriveBackupService::class, $drive);

        $this->artisan('backup:auto', ['--force' => true])->assertSuccessful();

        $schedule = app(BackupScheduleService::class);
        $this->assertCount(1, $schedule->listBackups());
        $this->assertSame('success', $schedule->lastRun()['status']);
        $this->assertSame(
            ['status' => 'failed', 'message' => 'Token has been expired or revoked.'],
            array_intersect_key($schedule->driveStatus(), ['status' => 1, 'message' => 1])
        );
    }

    public function test_drive_check_command(): void
    {
        $this->artisan('backup:drive-check')->assertSuccessful();
        $this->assertSame([], Storage::disk('google')->allFiles());

        config(['backup.google_drive.enabled' => false]);
        $this->artisan('backup:drive-check')->assertFailed();
    }

    public function test_backup_page_shows_drive_status(): void
    {
        $user = User::where('business_id', 1)->where('allow_login', 1)->whereNotNull('username')->first();
        if (! $user) {
            $this->markTestSkipped('No login user for business 1.');
        }
        config(['constants.administrator_usernames' => $user->username]);
        app(BackupScheduleService::class)->recordDriveRun(false, 'Drive quota exceeded');

        $this->actingAs($user)->get('/backup')
            ->assertOk()
            ->assertSee(__('backup.drive_copy'))
            ->assertSee('Drive quota exceeded')
            ->assertSee(__('backup.drive_kept_days', ['days' => 14]));

        config(['backup.google_drive.enabled' => false]);
        $this->actingAs($user)->get('/backup')->assertOk()->assertSee(__('backup.drive_disabled'));
    }

    private function useFilesOnlyBackupSource(): void
    {
        $source = storage_path('framework/testing/drive-backup-source');
        File::ensureDirectoryExists($source);
        File::put($source.'/note.txt', 'backup me');
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($source));

        config([
            'backup.backup.source.files.include' => [$source],
            'backup.backup.source.databases' => [],
            'backup.backup.password' => null,
        ]);
    }
}
