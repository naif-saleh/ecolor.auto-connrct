<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Call Commands
Schedule::command('app:make-call-command')->everySecond();
// Schedule::command('app:make-user-call-command')->everyMinute();;
// Call Status Commands
// Schedule::command('app:participants-command')->everySecond();
// Schedule::command('app:make-user-participant-command')->everySecond();
// Update User Status Commands
Schedule::command('app:update-user-status-command')->everySecond();
// BackUp Command
// Schedule::command('app:back-up-command')->daily();
// Schedule::command('app:back-up-command')->dailyAt('02:00');
// Schedule::command('app:back-up-command')->hourlyAt(30); // Run at a specific minute/hour
// Schedule::command('app:back-up-command')->weekdays(); // Runs on weekdays


