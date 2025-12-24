<?php

namespace App\Providers;

use App\Events\ConsultationBooked;
use App\Events\ConsultationCreated;
use Illuminate\Support\ServiceProvider;
use App\Listeners\SendConsultationNotification;

class EventServiceProvider extends ServiceProvider
{    protected $listen = [
        ConsultationBooked::class => [
            [SendConsultationNotification::class, 'handleConsultationBooked'],
        ],
    ];
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
