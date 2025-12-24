<?php

namespace App\Channels;

use App\Notifications\ConsultationRequestedNotification;
use App\Services\HypersenderService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class SmsChannel
{

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, ConsultationRequestedNotification $notification)
    {
        if (!method_exists($notification, 'toSms')) {
            Log::warning('Notification does not have toSms method', [
                'notification' => get_class($notification),
            ]);
            return;
        }
        $message = $notification->toSms($notifiable);
        $phoneNumber = $notifiable->phone_number;


    }
}

