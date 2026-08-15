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
use Illuminate\Support\Traits\Localizable;

class ConsultationReminderNotification extends Notification
{
    use Localizable, Queueable;
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
        return $this->withLocale($this->resolveLocale($notifiable), function () use ($notifiable) {
            $scheduledTime = $this->consultation->scheduled_at
                ? $this->consultation->scheduled_at->format('Y-m-d H:i')
                : __('notification.time_now');

            $otherName = $this->otherParty->full_name ?? $this->otherParty->name;

            $message = __(
                $this->recipientType === 'patient'
                    ? 'notification.reminder_mail_patient'
                    : 'notification.reminder_mail_doctor',
                ['name' => $otherName, 'time' => $scheduledTime]
            );

            Log::info($message);

            return (new MailMessage)
                ->subject(__('notification.consultation_reminder_subject'))
                ->line($message)
                ->line(__('notification.reminder_be_ready'))
                ->line(__('notification.reminder_thanks'))
                ->salutation(__('notification.salutation')."\n");
        });
    }

    public function toDatabase(object $notifiable)
    {
        // Not wrapped in withLocale() — unlike toMail() below, this stores
        // both languages up front (see App\Support\Locale::translateAll),
        // so there's no single "recipient's locale" to render for.
        $scheduledTime = $this->consultation->scheduled_at
            ? $this->consultation->scheduled_at->format('Y-m-d H:i')
            : 'now';

        $otherName = $this->otherParty->full_name ?? $this->otherParty->name;
        $messageKey = $this->recipientType === 'patient'
            ? 'notification.reminder_db_patient'
            : 'notification.reminder_db_doctor';

        $title = \App\Support\Locale::translateAll('notification.consultation_reminder_title');
        $message = \App\Support\Locale::translateAll($messageKey, ['name' => $otherName, 'time' => $scheduledTime]);

        return [
            'title' => $title['en'] ?? null,
            'title_ar' => $title['ar'] ?? null,
            'consultation_id' => $this->consultation->id,
            'recipient_type' => $this->recipientType,
            'other_party_id' => $this->otherParty->id,
            'other_party_name' => $otherName,
            'call_type' => $this->consultation->type,
            'scheduled_at' => optional($this->consultation->scheduled_at)->toIso8601String(),
            'message' => $message['en'] ?? null,
            'message_ar' => $message['ar'] ?? null,
        ];
    }

    protected function resolveLocale(object $notifiable): ?string
    {
        if (method_exists($notifiable, 'preferredLocale')) {
            return $notifiable->preferredLocale();
        }

        return app()->getLocale();
    }
}
