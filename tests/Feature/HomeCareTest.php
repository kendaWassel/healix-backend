<?php

namespace Tests\Feature;

use App\Models\CareProvider;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\HomeVisit;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the real home-care workflow, traced directly from the code (not
 * assumed): a DOCTOR requests a home visit after a consultation
 * (HomeVisitController::requestHomeVisit, routes/api/doctor.php) — patients
 * do not self-request an initial visit, only a re-request or a
 * replacement provider once one exists. A nurse/physiotherapist
 * (care_provider) then accepts, starts, and ends the session
 * (NurseController/NurseService — PhysiotherapistController mirrors it for
 * the other type). This area had only one incidental IDOR test
 * (AuthorizationHardeningTest) before this file — no coverage of the
 * actual accept/start/end workflow or its real business rules.
 */
class HomeCareTest extends TestCase
{
    use RefreshDatabase;

    private function doctorWithConsultation(): array
    {
        $doctorUser = User::create([
            'full_name' => 'Test Doctor', 'email' => 'doc-' . uniqid() . '@example.com',
            'phone' => '09' . random_int(10000000, 99999999), 'role' => 'doctor',
            'password' => 'password123', 'status' => 'approved', 'is_active' => true,
        ]);
        $doctorUser->markEmailAsVerified();
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);

        $patientUser = User::create([
            'full_name' => 'Test Patient', 'email' => 'pat-' . uniqid() . '@example.com',
            'phone' => '09' . random_int(10000000, 99999999), 'role' => 'patient',
            'password' => 'password123', 'status' => 'approved', 'is_active' => true,
        ]);
        $patientUser->markEmailAsVerified();
        $patient = Patient::create(['user_id' => $patientUser->id, 'gender' => 'female']);

        $consultation = Consultation::create([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id,
            'type' => 'schedule', 'status' => 'completed',
        ]);

        return [$doctorUser, $doctor, $patient, $consultation];
    }

    private function careProviderUser(string $type): array
    {
        $user = User::create([
            'full_name' => 'Test ' . $type, 'email' => strtolower($type) . '-' . uniqid() . '@example.com',
            'phone' => '09' . random_int(10000000, 99999999), 'role' => 'care_provider',
            'password' => 'password123', 'status' => 'approved', 'is_active' => true,
        ]);
        $user->markEmailAsVerified();
        $provider = CareProvider::factory()->create(['user_id' => $user->id, 'type' => $type]);

        return [$user->fresh(), $provider];
    }

    // --- requestHomeVisit: doctor-initiated ------------------------------------

    public function test_doctor_can_request_a_home_visit_for_their_own_patient(): void
    {
        [$doctorUser, , $patient, $consultation] = $this->doctorWithConsultation();

        $response = $this->actingAs($doctorUser, 'sanctum')
            ->postJson('/api/doctor/home-visit/request', [
                'consultation_id' => $consultation->id,
                'patient_id' => $patient->id,
                'service_type' => 'nurse',
                'scheduled_at' => '14:30',
            ]);

        $response->assertStatus(200)->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('home_visits', [
            'consultation_id' => $consultation->id,
            'patient_id' => $patient->id,
            'service_type' => 'nurse',
            'status' => 'pending',
        ]);
    }

    public function test_doctor_cannot_request_a_home_visit_for_a_consultation_that_is_not_theirs(): void
    {
        [, , $patient, $consultation] = $this->doctorWithConsultation();
        [$otherDoctorUser] = $this->doctorWithConsultation();

        $response = $this->actingAs($otherDoctorUser, 'sanctum')
            ->postJson('/api/doctor/home-visit/request', [
                'consultation_id' => $consultation->id,
                'patient_id' => $patient->id,
                'service_type' => 'nurse',
                'scheduled_at' => '14:30',
            ]);

        $response->assertStatus(403);
    }

    public function test_request_home_visit_rejects_an_invalid_service_type(): void
    {
        [$doctorUser, , $patient, $consultation] = $this->doctorWithConsultation();

        $this->actingAs($doctorUser, 'sanctum')
            ->postJson('/api/doctor/home-visit/request', [
                'consultation_id' => $consultation->id,
                'patient_id' => $patient->id,
                'service_type' => 'surgeon', // not in the nurse|physiotherapist enum
                'scheduled_at' => '14:30',
            ])->assertStatus(422);
    }

    public function test_request_home_visit_rejects_a_full_datetime_instead_of_time_only(): void
    {
        // Real, confirmed-by-code validation quirk: scheduled_at on THIS
        // endpoint is time-of-day only ('H:i'), not a full date+time —
        // worth locking in explicitly since it is easy to regress.
        [$doctorUser, , $patient, $consultation] = $this->doctorWithConsultation();

        $this->actingAs($doctorUser, 'sanctum')
            ->postJson('/api/doctor/home-visit/request', [
                'consultation_id' => $consultation->id,
                'patient_id' => $patient->id,
                'service_type' => 'nurse',
                'scheduled_at' => '2026-09-01 14:30',
            ])->assertStatus(422);
    }

    public function test_non_doctor_cannot_request_a_home_visit(): void
    {
        [, , $patient, $consultation] = $this->doctorWithConsultation();
        [$nurseUser] = $this->careProviderUser('nurse');

        $this->actingAs($nurseUser, 'sanctum')
            ->postJson('/api/doctor/home-visit/request', [
                'consultation_id' => $consultation->id,
                'patient_id' => $patient->id,
                'service_type' => 'nurse',
                'scheduled_at' => '14:30',
            ])->assertStatus(403);
    }

    // --- accept: type-matching is a real, enforced business rule ------------

    public function test_a_nurse_can_accept_a_pending_nurse_visit(): void
    {
        [, , $patient, $consultation] = $this->doctorWithConsultation();
        [$nurseUser, $nurse] = $this->careProviderUser('nurse');

        $visit = HomeVisit::factory()->create([
            'patient_id' => $patient->id,
            'consultation_id' => $consultation->id,
            'service_type' => 'nurse',
            'status' => 'pending',
            'care_provider_id' => null,
            'scheduled_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($nurseUser, 'sanctum')
            ->postJson("/api/provider/nurse/orders/{$visit->id}/accept");

        $response->assertStatus(200);
        $this->assertSame('accepted', $visit->fresh()->status);
        $this->assertSame($nurse->id, $visit->fresh()->care_provider_id);
    }

    public function test_a_physiotherapist_cannot_accept_a_nurse_type_visit(): void
    {
        // The real, code-confirmed rule: NurseService::acceptOrder() checks
        // BOTH that the caller is a nurse AND that visit->service_type is
        // 'nurse' — a physiotherapist hitting the nurse accept route is
        // rejected even though the route itself has no separate guard.
        [, , $patient, $consultation] = $this->doctorWithConsultation();
        [$physioUser] = $this->careProviderUser('physiotherapist');

        $visit = HomeVisit::factory()->create([
            'patient_id' => $patient->id,
            'consultation_id' => $consultation->id,
            'service_type' => 'nurse',
            'status' => 'pending',
            'care_provider_id' => null,
        ]);

        $response = $this->actingAs($physioUser, 'sanctum')
            ->postJson("/api/provider/nurse/orders/{$visit->id}/accept");

        $response->assertStatus(403);
        $this->assertSame('pending', $visit->fresh()->status);
    }

    public function test_accepting_an_already_accepted_visit_fails(): void
    {
        [, , $patient, $consultation] = $this->doctorWithConsultation();
        [, $firstNurse] = $this->careProviderUser('nurse');
        [$secondNurseUser] = $this->careProviderUser('nurse');

        $visit = HomeVisit::factory()->create([
            'patient_id' => $patient->id,
            'consultation_id' => $consultation->id,
            'service_type' => 'nurse',
            'status' => 'accepted',
            'care_provider_id' => $firstNurse->id,
        ]);

        $this->actingAs($secondNurseUser, 'sanctum')
            ->postJson("/api/provider/nurse/orders/{$visit->id}/accept")
            ->assertStatus(404);
    }

    // --- startSession: real business rules ------------------------------------

    public function test_nurse_can_start_a_session_already_due(): void
    {
        [, , $patient, $consultation] = $this->doctorWithConsultation();
        [$nurseUser, $nurse] = $this->careProviderUser('nurse');

        $visit = HomeVisit::factory()->create([
            'patient_id' => $patient->id,
            'consultation_id' => $consultation->id,
            'service_type' => 'nurse',
            'status' => 'accepted',
            'care_provider_id' => $nurse->id,
            'scheduled_at' => now()->subMinutes(5), // already due
        ]);

        $response = $this->actingAs($nurseUser, 'sanctum')
            ->postJson("/api/provider/nurse/schedules/{$visit->id}/start-session");

        $response->assertStatus(200);
        $this->assertSame('in_progress', $visit->fresh()->status);
        $this->assertNotNull($visit->fresh()->started_at);
    }

    public function test_nurse_cannot_start_a_session_before_its_scheduled_time(): void
    {
        [, , $patient, $consultation] = $this->doctorWithConsultation();
        [$nurseUser, $nurse] = $this->careProviderUser('nurse');

        $visit = HomeVisit::factory()->create([
            'patient_id' => $patient->id,
            'consultation_id' => $consultation->id,
            'service_type' => 'nurse',
            'status' => 'accepted',
            'care_provider_id' => $nurse->id,
            'scheduled_at' => now()->addHours(3), // not due yet
        ]);

        $response = $this->actingAs($nurseUser, 'sanctum')
            ->postJson("/api/provider/nurse/schedules/{$visit->id}/start-session");

        $response->assertStatus(400);
        $this->assertSame('accepted', $visit->fresh()->status);
    }

    public function test_a_different_nurse_cannot_start_a_session_they_do_not_own(): void
    {
        [, , $patient, $consultation] = $this->doctorWithConsultation();
        [, $ownerNurse] = $this->careProviderUser('nurse');
        [$otherNurseUser] = $this->careProviderUser('nurse');

        $visit = HomeVisit::factory()->create([
            'patient_id' => $patient->id,
            'consultation_id' => $consultation->id,
            'service_type' => 'nurse',
            'status' => 'accepted',
            'care_provider_id' => $ownerNurse->id,
            'scheduled_at' => now()->subMinutes(5),
        ]);

        $this->actingAs($otherNurseUser, 'sanctum')
            ->postJson("/api/provider/nurse/schedules/{$visit->id}/start-session")
            ->assertStatus(404);
    }

    // --- endSession ------------------------------------------------------------

    public function test_nurse_can_end_an_in_progress_session(): void
    {
        [, , $patient, $consultation] = $this->doctorWithConsultation();
        [$nurseUser, $nurse] = $this->careProviderUser('nurse');

        $visit = HomeVisit::factory()->create([
            'patient_id' => $patient->id,
            'consultation_id' => $consultation->id,
            'service_type' => 'nurse',
            'status' => 'in_progress',
            'care_provider_id' => $nurse->id,
            'started_at' => now()->subMinutes(30),
        ]);

        $response = $this->actingAs($nurseUser, 'sanctum')
            ->postJson("/api/provider/nurse/schedules/{$visit->id}/end-session");

        $response->assertStatus(200);
        $this->assertSame('completed', $visit->fresh()->status ?? $visit->fresh()->status);
        $this->assertNotNull($visit->fresh()->ended_at);
    }

    public function test_ending_a_session_that_was_never_started_fails(): void
    {
        [, , $patient, $consultation] = $this->doctorWithConsultation();
        [$nurseUser, $nurse] = $this->careProviderUser('nurse');

        $visit = HomeVisit::factory()->create([
            'patient_id' => $patient->id,
            'consultation_id' => $consultation->id,
            'service_type' => 'nurse',
            'status' => 'accepted', // never started
            'care_provider_id' => $nurse->id,
            'started_at' => null,
        ]);

        $this->actingAs($nurseUser, 'sanctum')
            ->postJson("/api/provider/nurse/schedules/{$visit->id}/end-session")
            ->assertStatus(404);
    }

    // --- access control ----------------------------------------------------

    public function test_unauthenticated_request_to_nurse_routes_is_rejected(): void
    {
        $this->getJson('/api/provider/nurse/schedules')->assertStatus(401);
    }

    public function test_patient_cannot_access_nurse_provider_routes(): void
    {
        [, , $patient] = $this->doctorWithConsultation();

        $this->actingAs($patient->user, 'sanctum')
            ->getJson('/api/provider/nurse/schedules')
            ->assertStatus(403);
    }
}
