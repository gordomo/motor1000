<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled tasks
Schedule::job(new \App\Jobs\SendAppointmentReminderJob)->hourly();
Schedule::command('reminders:process')->dailyAt('09:00');
Schedule::command('horizon:snapshot')->everyFiveMinutes();
