<?php

namespace App\Listeners;

use App\Events\ConsultationCreated;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\ConsultationRequestedNotification;

class SendConsultationNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ConsultationCreated $event): void
    {
        
        $event->doctor->notify(new ConsultationRequestedNotification(
            $event->consultation, 
            $event->patient
        ));    


    }
}
