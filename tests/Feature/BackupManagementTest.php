<?php

namespace Tests\Feature;

use App\Services\Backup\BackupScheduleService;
use App\System;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * POS Backup page: automatic schedule settings, archive list/download/delete, retention.
 */
class BackupManagementTest extends TestCase
{
    use DatabaseTransactions;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        config(['backup.backup.destination.disks' => ['backups']]);
        Storage::fake('backups');
        $this->dir = (string) config('backup.backup.name');
        System::where('key', 'like', 'backup_auto_%')->delete();
    }

    private function admin(): User
    {
        $user = User::where('business_id', 1)->where('allow_login', 1)->whereNotNull('username')->first();
        if (! $user) {
            $this->markTestSkipped('No login user for business 1.');
        }
        config(['constants.administrator_usernames' => $user->username]);

        return $user;
    }

    private function putArchive(string $name, int $minutesAgo): void
    {
        $path = $this->dir.'/'.$name;
        Storage::disk('backups')->put($path, 'zip');
        touch(Storage::disk('backups')->path($path), now()->subMinutes($minutesAgo)->getTimestamp());
    }

    public function test_defaults_to_hourly_enabled_full_backup(): void
    {
        $service = app(BackupScheduleService::class);

        $this->assertSame(
            ['enabled' => true, 'interval' => 'hourly', 'scope' => 'full', 'keep_count' => 24],
            $service->settings()
        );
        $this->assertSame('0 * * * *', $service->cronExpression());
        $this->assertSame(0, $service->nextRunAt()->minute);
    }

    public function test_disabled_schedule_has_no_next_run_and_command_skips(): void
    {
        $service = app(BackupScheduleService::class);
        $service->saveSettings(['enabled' => false, 'interval' => 'daily', 'scope' => 'db', 'keep_count' => 3]);

        $this->assertNull($service->nextRunAt());
        $this->artisan('backup:auto')
            ->expectsOutput('Automatic backups are disabled; nothing to do.')
            ->assertSuccessful();
        $this->assertNull($service->lastRun()['at']);
    }

    public function test_path_for_rejects_unknown_and_traversal_names(): void
    {
        $this->putArchive('2026-09-27-10-00-00.zip', 5);
        $service = app(BackupScheduleService::class);

        $this->assertSame($this->dir.'/2026-09-27-10-00-00.zip', $service->pathFor('2026-09-27-10-00-00.zip'));
        $this->assertNull($service->pathFor('../.env'));
        $this->assertNull($service->pathFor('missing.zip'));
        $this->assertNull($service->pathFor('notes.txt'));
    }

    public function test_cleanup_keeps_configured_number_of_newest_archives(): void
    {
        app(BackupScheduleService::class)
            ->saveSettings(['enabled' => true, 'interval' => 'hourly', 'scope' => 'full', 'keep_count' => 2]);
        $this->putArchive('2026-09-27-07-00-00.zip', 180);
        $this->putArchive('2026-09-27-08-00-00.zip', 120);
        $this->putArchive('2026-09-27-09-00-00.zip', 60);

        $this->artisan('backup:clean', ['--disable-notifications' => true])->assertSuccessful();

        $this->assertSame(
            ['2026-09-27-09-00-00.zip', '2026-09-27-08-00-00.zip'],
            array_column(app(BackupScheduleService::class)->listBackups(), 'file_name')
        );
    }

    public function test_index_lists_backups_and_schedule(): void
    {
        $this->putArchive('2026-09-27-09-00-00.zip', 60);

        $this->actingAs($this->admin())
            ->get('/backup')
            ->assertOk()
            ->assertSee('2026-09-27-09-00-00.zip')
            ->assertSee(route('backup.settings.update'), false);
    }

    public function test_settings_update_persists_and_validates(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put('/backup/settings', ['enabled' => '1', 'interval' => 'every_6_hours', 'scope' => 'db', 'keep_count' => 10])
            ->assertRedirect(route('backup.index'))
            ->assertSessionHas('status.success', 1);

        $this->assertSame(
            ['enabled' => true, 'interval' => 'every_6_hours', 'scope' => 'db', 'keep_count' => 10],
            app(BackupScheduleService::class)->settings()
        );

        $this->actingAs($admin)
            ->put('/backup/settings', ['interval' => 'every_second', 'scope' => 'db', 'keep_count' => 0])
            ->assertSessionHasErrors(['interval', 'keep_count']);
    }

    public function test_download_and_delete_archive(): void
    {
        $admin = $this->admin();
        $this->putArchive('2026-09-27-09-00-00.zip', 60);

        $this->actingAs($admin)
            ->get('/backup/download/2026-09-27-09-00-00.zip')
            ->assertOk()
            ->assertDownload('2026-09-27-09-00-00.zip');

        $this->actingAs($admin)->get('/backup/download/missing.zip')->assertNotFound();

        $this->actingAs($admin)
            ->delete('/backup/2026-09-27-09-00-00.zip')
            ->assertRedirect(route('backup.index'));
        Storage::disk('backups')->assertMissing($this->dir.'/2026-09-27-09-00-00.zip');
    }

    public function test_non_admin_is_forbidden(): void
    {
        $user = $this->admin();
        config(['constants.administrator_usernames' => '']);

        $this->actingAs($user)->get('/backup')->assertForbidden();
        $this->actingAs($user)
            ->put('/backup/settings', ['interval' => 'hourly', 'scope' => 'db', 'keep_count' => 5])
            ->assertForbidden();
    }
}
