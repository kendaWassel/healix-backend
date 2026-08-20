<?php

namespace App\Notifications;

use App\Models\DeliveryTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DeliveryDriverNearbyNotification extends Notification
{
    use Queueable;

    public function __construct(protected DeliveryTask $task) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $title = \App\Support\Locale::translateAll('notification.delivery_driver_nearby_title');
        $message = \App\Support\Locale::translateAll('notification.delivery_driver_nearby_body');

        return [
            'type' => 'delivery_driver_nearby',
            'title' => $title['en'] ?? null,
            'title_ar' => $title['ar'] ?? null,
            'message' => $message['en'] ?? null,
            'message_ar' => $message['ar'] ?? null,
            'delivery_task_id' => $this->task->id,
            'order_id' => $this->task->order_id,
        ];
    }
}
