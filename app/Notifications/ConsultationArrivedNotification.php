<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConsultationArrivedNotification extends Notification
{
    use Queueable;
    protected $consultation;
    protected $recipientType; // 'patient' or 'doctor'
    protected $otherParty; // The other person (doctor if recipient is patient, patient if recipient is doctor)
    

    /**
     * Create a new notification instance.
     */
    public function __construct($consultation, $recipientType, $otherParty)
    {
        $this->consultation = $consultation;
        $this->recipientType = $recipientType;
        $this->otherParty = $otherParty;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $scheduledTime = $this->consultation->scheduled_at 
            ? $this->consultation->scheduled_at->format('Y-m-d H:i') 
            : 'now';
        
        $otherName = $this->getOtherPartyName();
        
        if ($this->recipientType === 'patient') {
            $message = "Your consultation with Dr. {$otherName} is starting now (scheduled for {$scheduledTime})";
        } else {
            $message = "Your consultation with {$otherName} is starting now (scheduled for {$scheduledTime})";
        }

        return (new MailMessage)
                    ->subject('Consultation Starting Now')
                    ->line($message)
                    ->action('Join Consultation', url('/consultations/' . $this->consultation->id))
                    ->line('Thank you for using our application!');
    }

    public function toDatabase(object $notifiable)
    {
        $scheduledTime = $this->consultation->scheduled_at 
            ? $this->consultation->scheduled_at->format('Y-m-d H:i') 
            : 'now';
        
        $otherName = $this->getOtherPartyName();
        
        if ($this->recipientType === 'patient') {
            $title = 'Consultation Starting Now';
            $message = "Your consultation with Dr. {$otherName} is starting now";
        } else {
            $title = 'Consultation Starting Now';
            $message = "Your consultation with {$otherName} is starting now";
        }

        return [
            'title' => $title,
            'consultation_id' => $this->consultation->id,
            'recipient_type' => $this->recipientType,
            'other_party_id' => $this->getOtherPartyId(),
            'other_party_name' => $otherName,
            'call_type' => $this->consultation->type,
            'scheduled_at' => optional($this->consultation->scheduled_at)->toIso8601String(),
            'message' => $message,
        ];
    }

    public function toBroadcast($notifiable)
    {
        $scheduledTime = $this->consultation->scheduled_at 
            ? $this->consultation->scheduled_at->format('Y-m-d H:i') 
            : 'now';
        
        $otherName = $this->getOtherPartyName();
        
        if ($this->recipientType === 'patient') {
            $title = 'Consultation Starting Now';
            $message = "Your consultation with Dr. {$otherName} is starting now";
        } else {
            $title = 'Consultation Starting Now';
            $message = "Your consultation with {$otherName} is starting now";
        }

        return new BroadcastMessage([
            'title' => $title,
            'message' => $message,
            'consultation_id' => $this->consultation->id,
            'recipient_type' => $this->recipientType,
            'other_party_id' => $this->getOtherPartyId(),
            'other_party_name' => $otherName,
            'call_type' => $this->consultation->type,
            'scheduled_at' => optional($this->consultation->scheduled_at)->toIso8601String(),
        ]);
    }

    public function broadcastOn(): array
    {
        if ($this->recipientType === 'patient') {
            return [
                new PrivateChannel('user.' . $this->consultation->patient_id),
            ];
        } else {
            return [
                new PrivateChannel('doctor.' . $this->consultation->doctor_id),
            ];
        }
    }

    /**
     * Get the other party's name, handling both User and Patient models
     */
    protected function getOtherPartyName()
    {
        if (isset($this->otherParty->full_name)) {
            return $this->otherParty->full_name;
        }
        if (isset($this->otherParty->name)) {
            return $this->otherParty->name;
        }
        if (method_exists($this->otherParty, 'user') && $this->otherParty->user) {
            return $this->otherParty->user->full_name ?? $this->otherParty->user->name ?? 'Unknown';
        }
        return 'Unknown';
    }

    /**
     * Get the other party's ID
     */
    protected function getOtherPartyId()
    {
        if (isset($this->otherParty->id)) {
            return $this->otherParty->id;
        }
        if (method_exists($this->otherParty, 'user') && $this->otherParty->user) {
            return $this->otherParty->user->id;
        }
        return null;
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

