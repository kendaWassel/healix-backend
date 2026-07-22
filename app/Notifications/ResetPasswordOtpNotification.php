<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Delivers the 6-digit password reset code.
 *
 * Carries no link at all: the code is typed back into the mobile app, so there
 * is nothing clickable to phish and no deep link to configure.
 *
 * Not queued, matching the existing verification mail, so a reset works without
 * a running queue worker.
 */
class ResetPasswordOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $otp,
        public int $expiresInMinutes
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notification.otp_subject'))
            ->view('emails.password-reset-otp', [
                'user' => $notifiable,
                'otp' => $this->otp,
                'expiresInMinutes' => $this->expiresInMinutes,
            ]);
    }
}
