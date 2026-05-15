<?php

namespace App\Console;

use App\Jobs\SendAppointmentReminderJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Send appointment reminders every hour
        $schedule->job(new SendAppointmentReminderJob)->hourly();

        // Process pending reminder notifications daily at 9am
        $schedule->command('reminders:process')->dailyAt('09:00');

        // Horizon snapshot for metrics
        $schedule->command('horizon:snapshot')->everyFiveMinutes();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
