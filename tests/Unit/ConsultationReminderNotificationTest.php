<?php

namespace Tests\Unit;

use App\Models\Consultation;
use App\Models\User;
use App\Notifications\ConsultationReminderNotification;
use Carbon\Carbon;
use Tests\TestCase;

class ConsultationReminderNotificationTest extends TestCase
{
    public function test_it_uses_the_recipient_preferred_locale_for_database_message(): void
    {
        app()->setLocale('en');

        $user = User::factory()->make([
            'preferred_locale' => 'ar',
        ]);

        $consultation = Consultation::factory()->make([
            'id' => 43,
            'type' => 'schedule',
            'scheduled_at' => Carbon::parse('2026-08-14 02:30:00'),
        ]);

        $doctor = User::factory()->make([
            'full_name' => 'Melyna Swaniawski',
        ]);

        $notification = new ConsultationReminderNotification($consultation, 'patient', $doctor);
        $payload = $notification->toDatabase($user);

        $this->assertSame('تذكير بموعد استشارة', $payload['title']);
        $this->assertStringContainsString('لديك استشارة', $payload['message']);
        $this->assertStringNotContainsString('Consultation Reminder', $payload['title']);
        $this->assertStringNotContainsString('You have a consultation', $payload['message']);
    }
}
