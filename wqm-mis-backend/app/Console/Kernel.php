<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
         $schedule->command('handle:notify-expired-materials')->daily();
         $schedule->command('handle:notify-expired-laboratory-materials')->daily();
         $schedule->command('handle:material-expiration-command')->daily();
         $schedule->command('handle:laboratory-material-expiration-command')->daily();
         $schedule->command('handle:notify-asset-maintenance-schedule')->daily();
         $schedule->command('handle:notify-water-scheme-schedule')->daily();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
