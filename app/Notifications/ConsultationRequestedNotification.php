<?php

namespace App\Notifications;

use App\Mail\ConsultationBookedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Notifications\Notification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;


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
        ->subject('New Consultation Requested')
        ->markdown('emails.notifications.consultation-booked', [
            'consultation' => $this->consultation,
            'patient' => $this->patient,
            'doctor' => $this->consultation->doctor
        ]);

    }
    public function toDatabase(object $notifiable)
    {
        return [
            'title' => 'New Consultation Requested',
            'consultation_id' => $this->consultation->id,
            'patient_id' => $this->patient->id,
            'patient_name' => $this->patient->full_name ?? $this->patient->name,
            'call_type' => $this->consultation->type,
            'scheduled_at' => optional($this->consultation->scheduled_at)->toIso8601String(),
            'message' => 'A new consultation has been requested by ' . ($this->patient->full_name ?? $this->patient->name),

        ];
    }
    public function toSms(object $notifiable): array{
        $message = "New Consultation Requested by " . ($this->patient->full_name ?? $this->patient->name) . ".";
        if ($this->consultation->scheduled_at) {
            $message .= " Scheduled at: " . $this->consultation->scheduled_at->format('Y-m-d H:i');
        }
        $message .= " Type: " . ucfirst($this->consultation->type) . ".";
        return [
            'to' => $this->doctor->phone,
            'message' => $message,
        ];
    }
    


}
