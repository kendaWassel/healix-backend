<?php

namespace App\Channels;

use App\Notifications\ConsultationRequestedNotification;
use App\Services\TraccarSmsService;
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
    public function send($notifiable, Notification $notification)
    {
        if (method_exists($notification, 'toSms')) {
            $message = $notification->toSms($notifiable);

            if ($message) {
                $traccarSmsService = new TraccarSmsService();
                $traccarSmsService->sendSms($notifiable->phone, $message);
            }
        }
    }
}

