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
        $schedule->command('reports:rebuild-daily-summary --days=90')->dailyAt('00:20');
        $schedule->command('reminders:run --days=7')->dailyAt('07:00');
        $schedule->command('backup:database')->dailyAt('02:00');
        $schedule->command('invoices:retry')->hourly();
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
