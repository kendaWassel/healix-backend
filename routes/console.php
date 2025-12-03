<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule consultation reminder notifications to run every 5 minutes
// Sends reminders 10 minutes before consultation starts
Schedule::command('consultations:send-reminders --minutes=10 --window=5')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Schedule consultation arrival notifications to run every 5 minutes
Schedule::command('consultations:send-arrival-notifications --window=5')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
