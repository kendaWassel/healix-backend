<?php

namespace App\Providers;

use App\Channels\SmsChannel;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // // Register custom SMS channel
        // Notification::extend('sms', function ($app) {
        //     return new SmsChannel($app->make(\App\Services\HypersenderService::class));
        // });
    }
}
