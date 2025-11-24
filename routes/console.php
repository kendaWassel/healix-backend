<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule consultation reminder notifications to run every 15 minutes
Schedule::command('consultations:send-reminders --minutes=15 --window=15')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();
