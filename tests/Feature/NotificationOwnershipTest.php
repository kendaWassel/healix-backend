<?php

namespace Tests\Feature;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

/**
 * White Box: NotificationController::markAsRead() ownership — never
 * tested before (confirmed by grep: zero hits for
 * "markAsRead"/"NotificationController" across tests/). The internal
 * guard is IDOR-safe BY CONSTRUCTION: it scopes the lookup through
 * $user->notifications()->find($id) (Laravel's Notifiable relation),
 * so another user's notification id simply isn't found — never reachable
 * even with a correct id, unlike a naive DatabaseNotification::find($id).
 */
class NotificationOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_mark_another_users_notification_as_read(): void
    {
        $owner = Patient::factory()->create();
        $intruder = Patient::factory()->create();

        $notification = DatabaseNotification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\ConsultationRequestedNotification',
            'notifiable_type' => \App\Models\User::class,
            'notifiable_id' => $owner->user->id,
            'data' => ['message' => 'test'],
        ]);

        $response = $this->actingAs($intruder->user, 'sanctum')
            ->postJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(404);
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_a_user_can_mark_their_own_notification_as_read(): void
    {
        $owner = Patient::factory()->create();

        $notification = DatabaseNotification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\ConsultationRequestedNotification',
            'notifiable_type' => \App\Models\User::class,
            'notifiable_id' => $owner->user->id,
            'data' => ['message' => 'test'],
        ]);

        $response = $this->actingAs($owner->user, 'sanctum')
            ->postJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(200);
        $this->assertNotNull($notification->fresh()->read_at);
    }
}
