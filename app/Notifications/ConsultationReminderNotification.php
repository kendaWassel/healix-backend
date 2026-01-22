<?php

namespace App\Notifications;

use App\Services\UltraMsgService;
use Illuminate\Bus\Queueable;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ConsultationReminderNotification extends Notification
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
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $scheduledTime = $this->consultation->scheduled_at
            ? $this->consultation->scheduled_at->format('Y-m-d H:i')
            : 'now';

        $otherName = $this->otherParty->full_name ?? $this->otherParty->name;

        if ($this->recipientType === 'patient') {
            $message = "Reminder: You have a consultation with Dr. {$otherName} scheduled for {$scheduledTime}";
        } else {
            $message = "Reminder: You have a consultation with {$otherName} scheduled for {$scheduledTime}";
        }
        Log::info($message);
        return (new MailMessage)
            ->subject('Consultation Reminder')
            ->line($message)
            ->line('Please be ready for the consultation.')
            ->line('Thank you for using our platform!')
            ->salutation('Healix Team'."\n");

    }

    public function toDatabase(object $notifiable)
    {
        $scheduledTime = $this->consultation->scheduled_at
            ? $this->consultation->scheduled_at->format('Y-m-d H:i')
            : 'now';

        $otherName = $this->otherParty->full_name ?? $this->otherParty->name;

        if ($this->recipientType === 'patient') {
            $title = 'Consultation Reminder';
            $message = "You have a consultation with Dr. {$otherName} scheduled for {$scheduledTime}";
        } else {
            $title = 'Consultation Reminder';
            $message = "You have a consultation with {$otherName} scheduled for {$scheduledTime}";
        }

        return [
            'title' => $title,
            'consultation_id' => $this->consultation->id,
            'recipient_type' => $this->recipientType,
            'other_party_id' => $this->otherParty->id,
            'other_party_name' => $otherName,
            'call_type' => $this->consultation->type,
            'scheduled_at' => optional($this->consultation->scheduled_at)->toIso8601String(),
            'message' => $message,
        ];
    }
}
