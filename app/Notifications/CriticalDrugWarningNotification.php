<?php

namespace App\Notifications;

use App\Models\Prescription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CriticalDrugWarningNotification extends Notification
{
    use Queueable;

    public function __construct(protected Prescription $prescription) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $title = \App\Support\Locale::translateAll('notification.critical_warning_title');
        $message = \App\Support\Locale::translateAll('notification.critical_warning_body');

        return [
            'type' => 'critical_drug_warning',
            'title' => $title['en'] ?? null,
            'title_ar' => $title['ar'] ?? null,
            'message' => $message['en'] ?? null,
            'message_ar' => $message['ar'] ?? null,
            'prescription_id' => $this->prescription->id,
        ];
    }
}
