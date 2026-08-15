<?php

namespace App\Notifications;

use App\Models\Prescription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PrescriptionAcceptedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Prescription $prescription) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $title = \App\Support\Locale::translateAll('notification.prescription_accepted_title');
        $message = \App\Support\Locale::translateAll('notification.prescription_accepted_body');

        return [
            'type' => 'prescription_accepted',
            'title' => $title['en'] ?? null,
            'title_ar' => $title['ar'] ?? null,
            'message' => $message['en'] ?? null,
            'message_ar' => $message['ar'] ?? null,
            'prescription_id' => $this->prescription->id,
        ];
    }
}
