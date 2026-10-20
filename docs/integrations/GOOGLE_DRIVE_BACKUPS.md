# Google Drive backups (Laravel + spatie/laravel-backup)

Reusable guide for sending an **off-site copy** of every [spatie/laravel-backup](https://github.com/spatie/laravel-backup) archive to Google Drive with [yaza/laravel-google-drive-storage](https://github.com/yaza-putu/laravel-google-drive-storage). It is written so any Laravel project can follow it; the last section lists how the POS and Accounts apps apply it.

**Source of truth:** this file in the POS repo (`docs/integrations/GOOGLE_DRIVE_BACKUPS.md`). The Accounts repo keeps a copy in `docs/GOOGLE_DRIVE_BACKUPS.md`.

## How it works

1. Spatie's `backup:run` writes the archive to the **local** backup disk exactly as before.
2. After a **successful** run, the app streams the newest local `.zip` to the `google` disk (same relative path, e.g. `AppName/2026-10-20-01-00-00.zip`).
3. Drive copies older than `BACKUP_GOOGLE_DRIVE_KEEP_DAYS` are deleted; the newest Drive copy is always kept. Local archives follow the app's own retention.
4. The result (time, OK / failed, reason) is stored and shown on the app's backup page.

**Why Drive is not a Spatie destination:** in spatie/laravel-backup v8 and v9 a failing destination throws, so `backup:run` fails as a whole (and with `backup.backup.tries` > 1 it rebuilds the archive). Copying after the run keeps the local backup independent of Google outages, expired tokens or a full Drive.

## 1. Versions

| Laravel | PHP | Package version |
|---------|-----|-----------------|
| 10–12 | ≥ 8.2 | `yaza/laravel-google-drive-storage:^4.1` (V4.1.0 allows `illuminate/contracts` ^10–^12) |
| 13 | ≥ 8.3 | `^5.0` |
| 10 | 8.1 | `^2.0` |

Dependencies pulled in: `masbug/flysystem-google-drive-ext`, `google/apiclient`, `google/apiclient-services`, `google/auth`, `firebase/php-jwt`.

## 2. Google Cloud setup (once per Google account)

1. **Project:** [Google Cloud Console](https://console.cloud.google.com/) → create (or pick) a project.
2. **Enable the API:** *APIs & Services → Library → Google Drive API → Enable*.
3. **OAuth consent screen:** *APIs & Services → OAuth consent screen* (Google Auth Platform → Branding / Audience).
   - User type **External** (or Internal on Google Workspace).
   - Add your Google account as a test user while configuring.
   - **Publish the app ("In production").** Refresh tokens issued while the app is in *Testing* expire after **7 days**, which silently breaks backups a week later. Unverified apps are fine for your own account (you will see a "Google hasn't verified this app" warning → *Advanced → Continue*).
4. **OAuth client:** *Credentials → Create credentials → OAuth client ID*.
   - Type **Web application**.
   - Authorized redirect URI: `https://developers.google.com/oauthplayground`.
   - Copy the **Client ID** and **Client secret**.
5. **Refresh token** via the [OAuth 2.0 Playground](https://developers.google.com/oauthplayground):
   - Gear icon → tick **Use your own OAuth credentials** → paste client ID and secret.
   - Step 1: enter scope `https://www.googleapis.com/auth/drive` → *Authorize APIs* → sign in with the account that should own the backups.
   - Step 2: *Exchange authorization code for tokens* → copy the **Refresh token**.
   - Use the full `drive` scope: `drive.file` only sees files the app created, so it cannot reuse a folder you made in the Drive UI.
6. **Folder:** pick a path per app, e.g. `Spot Backups/POS` and `Spot Backups/Accounts`. The adapter creates missing folders. `GOOGLE_DRIVE_FOLDER` is a **path of folder names, not a folder ID** (an ID would be created as a folder literally named after the ID).

Refresh tokens stay valid until revoked, unused for 6 months, or the account changes its password with certain scopes. Google keeps at most 100 refresh tokens per client per account; generating more revokes the oldest, so don't regenerate tokens in a loop.

## 3. Composer

```bash
composer require yaza/laravel-google-drive-storage:^4.1
```

`google/apiclient-services` ships ~350 Google APIs. Keep only Drive by merging this into `composer.json`, then run `composer dump-autoload`:

```json
"scripts": {
    "pre-autoload-dump": ["Google\\Task\\Composer::cleanup"]
},
"extra": {
    "google/apiclient-services": ["Drive"]
}
```

Notes:
- **Windows `composer.bat`** strips `^` from arguments (`^4.1` becomes `4.1`). Quote it as `"^^4.1"` or fix the constraint in `composer.json` and run `composer update yaza/laravel-google-drive-storage`.
- **Docker** images that run `composer install --no-scripts` followed by `composer dump-autoload` still get the cleanup (the hook runs on `dump-autoload`).
- If your lock file was resolved for a newer PHP than your local CLI, add `--ignore-platform-req=php` locally; the production image must still satisfy every package's PHP constraint.

The package auto-registers the `google` filesystem driver (Laravel package discovery).

## 4. Configuration

### `.env` (and `.env.example` without values)

```env
BACKUP_GOOGLE_DRIVE_ENABLED=false
BACKUP_GOOGLE_DRIVE_KEEP_DAYS=14
GOOGLE_DRIVE_CLIENT_ID=
GOOGLE_DRIVE_CLIENT_SECRET=
GOOGLE_DRIVE_REFRESH_TOKEN=
# Folder path inside My Drive, created if missing (e.g. "Spot Backups/POS"); not a folder ID
GOOGLE_DRIVE_FOLDER=
# Optional Shared Drive ID (folder is then relative to that drive)
GOOGLE_DRIVE_TEAM_DRIVE_ID=
```

### `config/filesystems.php` → `disks`

The package's driver calls `setAccessToken()` for any **present** `accessToken` key and throws on an empty string, so blank optional keys must be removed, not left as `''`:

```php
'google' => array_filter([
    'driver' => 'google',
    'clientId' => env('GOOGLE_DRIVE_CLIENT_ID'),
    'clientSecret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
    'refreshToken' => env('GOOGLE_DRIVE_REFRESH_TOKEN'),
    'accessToken' => env('GOOGLE_DRIVE_ACCESS_TOKEN'),
    'folder' => env('GOOGLE_DRIVE_FOLDER'),
    'teamDriveId' => env('GOOGLE_DRIVE_TEAM_DRIVE_ID'),
], fn ($value) => $value !== null && $value !== ''),
```

Resolving the disk calls Google's token endpoint, so only resolve it when Drive is enabled (the service below does).

### `config/backup.php`

Leave `backup.destination.disks` local. Add at the top of the file:

```php
$googleDriveEnabled = filter_var(env('BACKUP_GOOGLE_DRIVE_ENABLED', false), FILTER_VALIDATE_BOOL)
    && filled(env('GOOGLE_DRIVE_CLIENT_ID'))
    && filled(env('GOOGLE_DRIVE_CLIENT_SECRET'))
    && filled(env('GOOGLE_DRIVE_REFRESH_TOKEN'));
```

and a top-level key:

```php
'google_drive' => [
    'enabled' => $googleDriveEnabled,
    'disk' => 'google',
    'keep_days' => max(1, (int) env('BACKUP_GOOGLE_DRIVE_KEEP_DAYS', 14)),
],
```

## 5. Code

### `app/Services/Backup/GoogleDriveBackupService.php`

Copy it from the POS repo (identical in Accounts). Public API:

| Method | Purpose |
|--------|---------|
| `enabled(): bool` / `keepDays(): int` | Read `config('backup.google_drive')` |
| `syncLatest(string $sourceDisk, string $directory): array{file, pruned}` | Upload newest local archive, then prune |
| `uploadLatest(...)` | Streams the newest `.zip` (no full file in memory) |
| `prune(string $directory): int` | One `listContents()` call (includes timestamps), deletes Drive copies older than `keepDays()`, never the newest |
| `check(): void` | Writes, reads back and deletes `.backup-drive-check.txt`; throws with the Drive error |
| `static describe(Throwable): string` | Extracts `error.message` / `error_description` from Google's JSON errors (max 500 chars, never tokens) |

It uses `Storage::disk('google')->getDriver()` (the Flysystem operator) instead of the Laravel adapter: the package builds the adapter with `throw = false`, which would turn failures into a silent `false`.

### `app/Console/Commands/CheckGoogleDriveBackupCommand.php`

`php artisan backup:drive-check` — fails when disabled, otherwise runs `check()` and prints the readable reason. Register it like any command (auto-discovered from `app/Console/Commands` in Laravel 11+ and in Kernels that call `$this->load(__DIR__.'/Commands')`).

### Hook it after the backup

Wherever the app runs `backup:run`, after a successful exit code:

```php
if ($drive->enabled()) {
    try {
        $result = $drive->syncLatest($localDiskName, (string) config('backup.backup.name'));
        // store: success, now(), "Uploaded {$result['file']}; removed {$result['pruned']} old Drive copies."
    } catch (\Throwable $e) {
        Log::error('Google Drive backup copy failed', ['exception' => $e]);
        // store: failed, now(), GoogleDriveBackupService::describe($e)
    }
}
```

Never return a failure for the backup itself when only the Drive copy failed; show the Drive status separately on the backup page.

## 6. Scheduler and Docker

- The copy runs inside the same process as the backup (scheduler container / cron). No queue worker is needed.
- Env is read when a container starts: after editing `.env`, recreate the web, queue and scheduler containers (e.g. `docker compose up -d --force-recreate app app-queue app-scheduler`).
- Then run `php artisan backup:drive-check` inside the app container and trigger one backup.
- Large archives use resumable chunked uploads (handled by the adapter); make sure the scheduler's command timeout / `withoutOverlapping()` window covers upload time.

## 7. Tests (no Google calls)

Fake the disk: `Storage::fake('google')` replaces it with a local temp disk, so `listContents`, timestamps (`touch()`), writes and deletes behave like Drive. Cover at least:

1. `google_drive.enabled` is true only with the flag **and** all three credentials (re-`require config_path('backup.php')` with `$_SERVER`/`$_ENV` overrides).
2. `syncLatest()` uploads the newest local archive and deletes Drive copies older than `keep_days`.
3. `prune()` never deletes the newest Drive copy, even when it is older than the cutoff.
4. A throwing Drive (mock `GoogleDriveBackupService::syncLatest`) leaves the backup successful and records the failed Drive status with the readable message.
5. `backup:drive-check` passes on the fake disk (and leaves no probe file) and fails when disabled.
6. The backup page shows the Drive status row.

Reference implementations: `tests/Feature/GoogleDriveBackupTest.php` in both repos.

## 8. Security

- Tokens live only in `.env` / the server's secret store — never in git, logs or the UI. `describe()` returns Google's message only.
- Use a dedicated Google account (or Shared Drive) for backups and one folder per app.
- **Encrypt archives** with `BACKUP_ARCHIVE_PASSWORD` (spatie AES-256) before sending them off-site, especially when backups include `.env` or uploads.
- To rotate: revoke access at [myaccount.google.com/permissions](https://myaccount.google.com/permissions) (or delete the OAuth client), generate a new refresh token, update `.env`, recreate containers, run `backup:drive-check`.

## 9. Troubleshooting

| Symptom (Drive status / `backup:drive-check`) | Cause / fix |
|---|---|
| `invalid_grant` / "Token has been expired or revoked" | Consent screen still in **Testing** (7-day tokens), token revoked, or >100 tokens issued. Publish the app and generate a new refresh token. |
| "Request is missing required authentication credential" | Wrong client ID/secret or refresh token from a different OAuth client. |
| "insufficient authentication scopes" / files not visible | Token created with `drive.file` or another scope; regenerate with `https://www.googleapis.com/auth/drive`. |
| Folder named like an ID appears in Drive | `GOOGLE_DRIVE_FOLDER` must be a folder path, not an ID. |
| "storage quota has been exceeded" | Drive is full: lower `BACKUP_GOOGLE_DRIVE_KEEP_DAYS`, back up less often / database-only, or use a larger Drive / Shared Drive. |
| Uploads land in the wrong of two same-named folders | Drive allows duplicate names; the adapter uses the oldest match. Rename or delete the duplicate. |
| Drive disabled although keys are set | All of `BACKUP_GOOGLE_DRIVE_ENABLED=true`, client ID, secret and refresh token are required; recreate containers after editing `.env`; run `php artisan config:clear` if config is cached outside Docker. |

## Applied in this repo set

| | POS (`posgamesspoteg`) | Accounts (`gamessspot`) |
|---|---|---|
| Laravel / spatie backup | 11 / 8.x | 12 / 9.x |
| Local disk + folder | `backups` → `storage/app/backups/UltimatePOS` | `local` → `storage/app/<APP_NAME>` |
| Hook | `App\Console\Commands\RunAutomaticBackup` (`backup:auto`, scheduled + "Backup now") | `App\Services\SystemBackupService::createDatabaseBackup()` (scheduled + "Create backup now") |
| Drive status storage | `system` table: `backup_auto_drive_last_at/_status/_message` | `system_ops_settings.drive_last_at/_status/_message` |
| UI | POS → Backup → Status → **Google Drive copy** | System Ops → Backups → **Google Drive copy** |
| Suggested folder | `Spot Backups/POS` | `Spot Backups/Accounts` |

Staging VPS steps: `docs/public-site/STAGING_VPS.md`. POS env reference: `docs/CONFIGURATION.md` → POS backups.
