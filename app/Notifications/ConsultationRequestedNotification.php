<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;


class ConsultationRequestedNotification extends Notification
{
    use Queueable;
    protected $consultation;
    protected $patient;

    public function __construct($consultation, $patient, $doctor)
    {
        $this->consultation = $consultation;
        $this->patient = $patient;
        $this->doctor=$consultation->doctor;
    }
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        
    $patientName = $this->patient->full_name ?? $this->patient->name;
    $scheduledAt = optional($this->consultation->scheduled_at);

    return (new MailMessage)
        ->subject(__('notification.consultation_requested_subject'))
        ->markdown('emails.notifications.consultation-booked', [
            'consultation' => $this->consultation,
            'patient' => $this->patient,
            'doctor' => $this->consultation->doctor
        ]);

    }
    public function toDatabase(object $notifiable)
    {
        $title = \App\Support\Locale::translateAll('notification.consultation_requested_title');
        $message = \App\Support\Locale::translateAll('notification.consultation_requested', [
            'name' => $this->patient->full_name ?? $this->patient->name,
        ]);

        return [
            'title' => $title['en'] ?? null,
            'title_ar' => $title['ar'] ?? null,
            'consultation_id' => $this->consultation->id,
            'patient_id' => $this->patient->id,
            'patient_name' => $this->patient->full_name ?? $this->patient->name,
            'call_type' => $this->consultation->type,
            'scheduled_at' => optional($this->consultation->scheduled_at)->toIso8601String(),
            'message' => $message['en'] ?? null,
            'message_ar' => $message['ar'] ?? null,
        ];
    }
    public function toSms(object $notifiable): array{
        $message = __('notification.sms_consultation_requested', [
            'name' => $this->patient->full_name ?? $this->patient->name,
        ]);

        if ($this->consultation->scheduled_at) {
            $message .= __('notification.sms_scheduled_at', [
                'time' => $this->consultation->scheduled_at->format('Y-m-d H:i'),
            ]);
        }

        $message .= __('notification.sms_call_type', [
            'type' => \App\Support\Locale::label('consultation_type', $this->consultation->type),
        ]);
        return [
            'to' => $this->doctor->phone,
            'message' => $message,
        ];
    }
    


}
