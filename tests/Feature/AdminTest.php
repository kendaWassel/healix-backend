<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers AdminController (dashboard, users list, approve/reject/edit/delete,
 * attachments, service-quality report). ZERO test coverage existed for the
 * whole `/api/admin/*` surface before this file (confirmed by grep across
 * tests/ for "/admin/" prior to writing this).
 */
class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = User::create([
            'full_name' => 'Test Admin',
            'email' => 'admin-' . uniqid() . '@example.com',
            'phone' => '09' . random_int(10000000, 99999999),
            'role' => 'admin',
            'password' => 'password123',
            'status' => 'approved',
            'is_active' => true,
        ]);
        $user->markEmailAsVerified();

        return $user->fresh();
    }

    private function patientUser(array $overrides = []): User
    {
        $user = User::create(array_merge([
            'full_name' => 'Test Patient',
            'email' => 'patient-' . uniqid() . '@example.com',
            'phone' => '09' . random_int(10000000, 99999999),
            'role' => 'patient',
            'password' => 'password123',
            'status' => 'approved',
            'is_active' => true,
        ], $overrides));
        $user->markEmailAsVerified();
        Patient::create(['user_id' => $user->id, 'gender' => 'female']);

        return $user->fresh();
    }

    // --- access control ------------------------------------------------------

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/admin/dashboard')->assertStatus(401);
    }

    public function test_non_admin_role_cannot_access_admin_routes(): void
    {
        $patient = $this->patientUser();

        $this->actingAs($patient, 'sanctum')
            ->getJson('/api/admin/dashboard')
            ->assertStatus(403);
    }

    // --- dashboard -------------------------------------------------------------

    public function test_admin_can_view_dashboard_statistics(): void
    {
        $admin = $this->adminUser();
        $this->patientUser();
        $this->patientUser();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'data' => ['users', 'consultations', 'orders', 'revenue', 'pending_documents', 'top_providers'],
            ]);
        // 2 patients created above + the admin itself is not counted as a patient.
        $this->assertSame(2, $response->json('data.users.patients'));
    }

    // --- users list & filters ----------------------------------------------

    public function test_admin_can_list_users(): void
    {
        $admin = $this->adminUser();
        $this->patientUser();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users')
            ->assertStatus(200)
            ->assertJsonStructure(['status', 'data', 'meta' => ['current_page', 'per_page', 'last_page', 'total']]);
    }

    public function test_admin_can_filter_users_by_role(): void
    {
        $admin = $this->adminUser();
        $this->patientUser();
        Doctor::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/users?role=doctor');

        $response->assertStatus(200);
        foreach ($response->json('data') as $row) {
            $this->assertSame('doctor', $row['user_type']);
        }
    }

    public function test_admin_can_filter_users_by_status(): void
    {
        $admin = $this->adminUser();
        $this->patientUser(['status' => 'pending', 'is_active' => false]);
        $this->patientUser(['status' => 'approved', 'is_active' => true]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/users?status=pending');

        $response->assertStatus(200);
        foreach ($response->json('data') as $row) {
            $this->assertSame('pending', $row['status']);
        }
    }

    // --- approve / reject ----------------------------------------------------

    public function test_admin_can_approve_a_pending_user(): void
    {
        $admin = $this->adminUser();
        $pendingUser = $this->patientUser(['status' => 'pending', 'is_active' => false]);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$pendingUser->id}/approve");

        $response->assertStatus(200)->assertJsonPath('status', 'success');
        $this->assertSame('approved', $pendingUser->fresh()->status);
        $this->assertTrue((bool) $pendingUser->fresh()->is_active);
    }

    public function test_approving_an_already_approved_user_is_idempotent(): void
    {
        $admin = $this->adminUser();
        $approvedUser = $this->patientUser(); // already approved/active by default

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$approvedUser->id}/approve");

        $response->assertStatus(200);
        $this->assertSame('approved', $approvedUser->fresh()->status);
    }

    public function test_approve_nonexistent_user_returns_not_found(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/users/999999/approve')
            ->assertStatus(404);
    }

    public function test_admin_can_reject_a_pending_user(): void
    {
        $admin = $this->adminUser();
        $pendingUser = $this->patientUser(['status' => 'pending', 'is_active' => false]);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$pendingUser->id}/reject");

        $response->assertStatus(200);
        $this->assertSame('rejected', $pendingUser->fresh()->status);
        $this->assertFalse((bool) $pendingUser->fresh()->is_active);
    }

    public function test_non_admin_cannot_approve_users(): void
    {
        $patient = $this->patientUser();
        $target = $this->patientUser(['status' => 'pending', 'is_active' => false]);

        $this->actingAs($patient, 'sanctum')
            ->patchJson("/api/admin/users/{$target->id}/approve")
            ->assertStatus(403);

        $this->assertSame('pending', $target->fresh()->status);
    }

    // --- edit ------------------------------------------------------------------

    public function test_admin_can_edit_an_active_user(): void
    {
        $admin = $this->adminUser();
        $activeUser = $this->patientUser();

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/users/{$activeUser->id}/edit", ['full_name' => 'Updated Name']);

        $response->assertStatus(200);
        $this->assertSame('Updated Name', $activeUser->fresh()->full_name);
    }

    public function test_editing_an_inactive_user_is_forbidden(): void
    {
        // Real business rule confirmed by reading AdminController::editUser()
        // directly: only accounts with is_active=true may be edited at all.
        $admin = $this->adminUser();
        $pendingUser = $this->patientUser(['status' => 'pending', 'is_active' => false]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/users/{$pendingUser->id}/edit", ['full_name' => 'Should Not Apply']);

        $response->assertStatus(403);
        $this->assertNotSame('Should Not Apply', $pendingUser->fresh()->full_name);
    }

    public function test_edit_validates_email_uniqueness(): void
    {
        $admin = $this->adminUser();
        $userA = $this->patientUser();
        $userB = $this->patientUser();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/users/{$userB->id}/edit", ['email' => $userA->email])
            ->assertStatus(422);
    }

    // --- delete ------------------------------------------------------------

    public function test_admin_can_delete_an_active_user(): void
    {
        $admin = $this->adminUser();
        $activeUser = $this->patientUser();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/users/{$activeUser->id}/delete")
            ->assertStatus(200);

        $this->assertDatabaseMissing('users', ['id' => $activeUser->id]);
    }

    public function test_deleting_an_inactive_user_is_forbidden(): void
    {
        $admin = $this->adminUser();
        $pendingUser = $this->patientUser(['status' => 'pending', 'is_active' => false]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/users/{$pendingUser->id}/delete")
            ->assertStatus(403);

        $this->assertDatabaseHas('users', ['id' => $pendingUser->id]);
    }

    // --- attachments -------------------------------------------------------

    public function test_attachments_for_a_nonexistent_user_returns_not_found(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users/999999/attachments')
            ->assertStatus(404);
    }

    public function test_admin_can_view_a_real_users_attachments(): void
    {
        $admin = $this->adminUser();
        $target = $this->patientUser();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/users/{$target->id}/attachments");

        $response->assertStatus(200)->assertJsonPath('status', 'success');
    }

    // --- service quality report ---------------------------------------------

    public function test_services_report_lists_a_completed_consultation_rating(): void
    {
        $admin = $this->adminUser();
        $patient = $this->patientUser();
        $doctor = Doctor::factory()->create();

        $consultation = Consultation::create([
            'patient_id' => Patient::where('user_id', $patient->id)->first()->id,
            'doctor_id' => $doctor->id,
            'type' => 'schedule',
            'status' => 'completed',
        ]);

        Rating::create([
            'user_id' => $patient->id,
            'target_type' => 'doctor',
            'target_id' => $doctor->id,
            'consultation_id' => $consultation->id,
            'stars' => 5,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/services');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }
}
