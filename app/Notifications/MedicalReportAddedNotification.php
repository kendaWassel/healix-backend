<?php

namespace App\Notifications;

use App\Models\MedicalRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MedicalReportAddedNotification extends Notification
{
    use Queueable;

    public function __construct(protected MedicalRecord $medicalRecord) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $title = \App\Support\Locale::translateAll('notification.medical_report_added_title');
        $message = \App\Support\Locale::translateAll('notification.medical_report_added_body');

        return [
            'type' => 'medical_report_added',
            'title' => $title['en'] ?? null,
            'title_ar' => $title['ar'] ?? null,
            'message' => $message['en'] ?? null,
            'message_ar' => $message['ar'] ?? null,
            'medical_record_id' => $this->medicalRecord->id,
            'patient_id' => $this->medicalRecord->patient_id,
        ];
    }
}
