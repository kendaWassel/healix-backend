<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers ConsultationController::startConsultation/endConsultation
 * (routes/api.php: POST /api/consultations/{id}/call and /end) —
 * confirmed by grep this had ZERO test coverage before this file, despite
 * being the "doctor completes the consultation" step of the real
 * patient-journey workflow. Booking itself is already covered by
 * ConsultationFeatureTest/ConsultationLinksAssessmentTest/
 * ConsultationSpecialtyValidationTest — not duplicated here.
 *
 * Real, code-confirmed quirk worth knowing: these two routes carry no
 * `role:` middleware at all (just auth:sanctum+verified) — ownership is
 * enforced entirely inside ConsultationService by filtering the query on
 * the caller's own doctor_id/patient_id, so a non-owning caller doesn't
 * get a 403, they get a 404 (the row simply isn't found for them). A user
 * with neither a doctor nor a patient profile (admin, pharmacist, ...) gets
 * a 403 at the very first guard instead.
 */
class ConsultationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function makeConsultation(array $overrides = []): Consultation
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();

        return Consultation::create(array_merge([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'type' => 'call_now',
            'status' => 'pending',
            'scheduled_at' => now(),
        ], $overrides));
    }

    // --- start (call) ------------------------------------------------------

    public function test_patient_can_start_a_pending_call_now_consultation(): void
    {
        $consultation = $this->makeConsultation();

        $response = $this->actingAs($consultation->patient->user, 'sanctum')
            ->postJson("/api/consultations/{$consultation->id}/call");

        $response->assertStatus(200)->assertJsonPath('data.status', 'in_progress');
        $this->assertDatabaseHas('consultations', ['id' => $consultation->id, 'status' => 'in_progress']);
    }

    public function test_doctor_can_start_the_same_pending_consultation(): void
    {
        $consultation = $this->makeConsultation();

        $response = $this->actingAs($consultation->doctor->user, 'sanctum')
            ->postJson("/api/consultations/{$consultation->id}/call");

        $response->assertStatus(200)->assertJsonPath('data.role', 'doctor');
    }

    public function test_starting_an_already_in_progress_consultation_returns_joining_not_a_restart(): void
    {
        $consultation = $this->makeConsultation(['status' => 'in_progress']);

        $response = $this->actingAs($consultation->patient->user, 'sanctum')
            ->postJson("/api/consultations/{$consultation->id}/call");

        $response->assertStatus(200)->assertJsonPath('message', __('consultation.joined_started'));
    }

    public function test_a_scheduled_consultation_cannot_be_started_before_its_time(): void
    {
        // Real rule (ConsultationService::startConsultation): now() must be
        // strictly AFTER scheduled_at, not merely equal to it.
        $consultation = $this->makeConsultation([
            'type' => 'schedule',
            'scheduled_at' => now()->addHour(),
        ]);

        $response = $this->actingAs($consultation->patient->user, 'sanctum')
            ->postJson("/api/consultations/{$consultation->id}/call");

        $response->assertStatus(409);
        $this->assertDatabaseHas('consultations', ['id' => $consultation->id, 'status' => 'pending']);
    }

    public function test_a_user_with_no_doctor_or_patient_profile_is_rejected(): void
    {
        $consultation = $this->makeConsultation();
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/consultations/{$consultation->id}/call");

        $response->assertStatus(403);
    }

    public function test_a_different_patient_cannot_start_someone_elses_consultation(): void
    {
        $consultation = $this->makeConsultation();
        $otherPatient = Patient::factory()->create();

        $response = $this->actingAs($otherPatient->user, 'sanctum')
            ->postJson("/api/consultations/{$consultation->id}/call");

        // Not a 403 — the ownership-scoped query simply finds no row for
        // this caller, which the controller reports as 404.
        $response->assertStatus(404);
    }

    // --- end -----------------------------------------------------------

    public function test_doctor_can_end_an_in_progress_consultation(): void
    {
        $consultation = $this->makeConsultation(['status' => 'in_progress']);

        $response = $this->actingAs($consultation->doctor->user, 'sanctum')
            ->postJson("/api/consultations/{$consultation->id}/end");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.ended_by', 'doctor');
        $this->assertDatabaseHas('consultations', ['id' => $consultation->id, 'status' => 'completed']);
    }

    public function test_patient_can_end_an_in_progress_consultation(): void
    {
        $consultation = $this->makeConsultation(['status' => 'in_progress']);

        $response = $this->actingAs($consultation->patient->user, 'sanctum')
            ->postJson("/api/consultations/{$consultation->id}/end");

        $response->assertStatus(200)->assertJsonPath('data.ended_by', 'patient');
    }

    public function test_a_pending_consultation_cannot_be_ended(): void
    {
        $consultation = $this->makeConsultation(['status' => 'pending']);

        $response = $this->actingAs($consultation->doctor->user, 'sanctum')
            ->postJson("/api/consultations/{$consultation->id}/end");

        $response->assertStatus(409);
    }

    public function test_ending_someone_elses_consultation_is_not_found_not_forbidden(): void
    {
        $consultation = $this->makeConsultation(['status' => 'in_progress']);
        $otherDoctor = Doctor::factory()->create();

        $response = $this->actingAs($otherDoctor->user, 'sanctum')
            ->postJson("/api/consultations/{$consultation->id}/end");

        $response->assertStatus(404);
    }

    public function test_ending_an_already_completed_consultation_is_rejected(): void
    {
        $consultation = $this->makeConsultation(['status' => 'completed']);

        $response = $this->actingAs($consultation->doctor->user, 'sanctum')
            ->postJson("/api/consultations/{$consultation->id}/end");

        $response->assertStatus(409);
    }
}
