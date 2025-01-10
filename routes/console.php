<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('app:make-call-command')->everySecond();
Schedule::command('app:participants-command')->everySecond();
Schedule::command('app:make-user-call-command')->everySecond();
// Schedule::command('app:make-user-participant-command')->everySecond();
Schedule::command('app:update-user-status-command')->everySecond();

