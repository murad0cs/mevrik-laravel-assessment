<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Clean up old files daily at 2:00 AM
        $schedule->command('storage:cleanup --days=30')
            ->daily()
            ->at('02:00')
            ->onSuccess(function () {
                \Log::info('Storage cleanup completed successfully');
            })
            ->onFailure(function () {
                \Log::error('Storage cleanup failed');
            });

        // Clean up very old files weekly with longer retention
        $schedule->command('storage:cleanup --days=90')
            ->weekly()
            ->sundays()
            ->at('03:00');

        // Monitor queue health every 5 minutes
        $schedule->call(function () {
            $pendingJobs = \DB::table('jobs')->count();
            $failedJobs = \DB::table('failed_jobs')->count();

            if ($pendingJobs > 100) {
                \Log::warning('Queue backlog detected', ['pending_jobs' => $pendingJobs]);
            }

            if ($failedJobs > 50) {
                \Log::error('High number of failed jobs', ['failed_jobs' => $failedJobs]);
            }
        })->everyFiveMinutes();

        // Restart queue workers daily to prevent memory leaks
        $schedule->command('queue:restart')
            ->daily()
            ->at('04:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
