<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConsultationRequestedNotification extends Notification
{
    use Queueable;
    protected $consultation;
    protected $patient;
    

    /**
     * Create a new notification instance.
     */
    public function __construct($consultation, $patient)
    {
        $this->consultation = $consultation;
        $this->patient = $patient;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database','broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->line('The introduction to the notification.')
                    ->action('Notification Action', url('/'))
                    ->line('Thank you for using our application!');
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

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'title' => 'New Consultation Requested',
            'message' => 'A new consultation has been requested by ' . ($this->patient->full_name ?? $this->patient->name),
            'consultation_id' => $this->consultation->id,
            'patient_id' => $this->patient->id,
            'patient_name' => $this->patient->full_name ?? $this->patient->name,
            'call_type' => $this->consultation->type,
            'scheduled_at' => optional($this->consultation->scheduled_at)->toIso8601String(),
        ]);
        
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('doctor.' . $this->consultation->doctor_id),
        ];
    }


    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
