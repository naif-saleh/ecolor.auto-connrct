<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

/**
 * Scheduled Console Commands
 *
 * These commands are scheduled to run at specific intervals
 * to handle automated tasks related to call processing,
 * participant tracking, and user status updates.
 *
 * Call Commands:
 * - app:ADial-make-call-command → Executes every second.
 * - app:ADist-make-call-command → Executes every minute.
 *
 * Call Status Commands:
 * - app:ADial-participants-command → Executes every second.
 * - app:ADist-participants-command → Executes every second.
 *
 * User Status Update Commands:
 * - app:ADist-update-user-status-command → Executes every second.
 */


Schedule::command('app:ADial-make-call-command')->everySecond();
Schedule::command('app:ADist-make-call-command')->everySecond();
// Schedule::command('app:ADist-make-call-command')->everyMinute();

// // Call Status Commands
Schedule::command('app:ADial-participants-command')->everySecond();
//Schedule::command('app:ADist-participants-command')->everySecond();

// // Update User Status Commands
Schedule::command('app:ADist-update-user-status-command')->everySecond();



// BackUp Command
// Schedule::command('app:back-up-command')->daily();
// Schedule::command('app:back-up-command')->dailyAt('02:00');
// Schedule::command('app:back-up-command')->hourlyAt(30); // Run at a specific minute/hour
// Schedule::command('app:back-up-command')->weekdays(); // Runs on weekdays
