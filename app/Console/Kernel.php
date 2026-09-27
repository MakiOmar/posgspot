<?php

namespace App\Console;

use App\Services\Backup\BackupScheduleService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $env = config('app.env');
        $email = config('mail.username');

        if ($env !== 'demo') {
            $this->scheduleAutomaticBackup($schedule);
        }

        if ($env === 'live') {
            //Schedule to create recurring invoices
            $schedule->command('pos:generateSubscriptionInvoices')->dailyAt('23:30');
            $schedule->command('pos:updateRewardPoints')->dailyAt('23:45');

            $schedule->command('pos:autoSendPaymentReminder')->dailyAt('8:00');

        }

        if ($env === 'demo') {
            //IMPORTANT NOTE: This command will delete all business details and create dummy business, run only in demo server.
            $schedule->command('pos:dummyBusiness')
                    ->cron('0 */3 * * *')
                    //->everyThirtyMinutes()
                    ->emailOutputTo($email);
        }
    }

    /**
     * Interval comes from the POS Backup page (system table), default hourly.
     */
    private function scheduleAutomaticBackup(Schedule $schedule): void
    {
        try {
            $backups = app(BackupScheduleService::class);
            if (! $backups->settings()['enabled']) {
                return;
            }
            $cron = $backups->cronExpression();
        } catch (\Throwable $e) {
            // DB unavailable (install, maintenance) must not break every artisan command.
            Log::warning('Automatic backup not scheduled: '.$e->getMessage());

            return;
        }

        $schedule->command('backup:auto')
            ->cron($cron)
            ->withoutOverlapping(180)
            ->runInBackground();
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
