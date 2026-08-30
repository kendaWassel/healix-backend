<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\HomeVisit;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * White Box coverage for two PatientService/Request internal branches
 * with zero prior test coverage (confirmed by grep before writing this).
 */
class PatientInternalLogicTest extends TestCase
{
    use RefreshDatabase;

    /**
     * PatientService::requestNewCareProvider() only allows re-requesting a
     * visit whose status is 'canceled' — any other status (e.g. still
     * 'pending') must be rejected (homevisit.only_cancelled_re_request, 400).
     */
    public function test_re_requesting_a_home_visit_that_is_not_cancelled_is_rejected(): void
    {
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();
        $visit = HomeVisit::factory()->create([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'status' => 'pending',
        ]);

        $response = $this->actingAs($patient->user, 'sanctum')
            ->postJson("/api/patient/home-visits/{$visit->id}/request-new-care-provider", [
                'scheduled_at' => now()->addDay()->toDateTimeString(),
            ]);

        $response->assertStatus(400);
        $this->assertDatabaseCount('home_visits', 1);
    }

    /**
     * UpdatePregnancyInfoRequest's own after-validator: is_pregnant=true is
     * rejected (422) for a non-female patient. Real, code-confirmed rule —
     * verified against the request class directly, not assumed.
     */
    public function test_a_male_patient_cannot_record_is_pregnant_true(): void
    {
        $patient = Patient::factory()->create(['gender' => 'male']);

        $response = $this->actingAs($patient->user, 'sanctum')
            ->putJson('/api/patient/medical-record/pregnancy', ['is_pregnant' => true]);

        $response->assertStatus(422);
    }

    public function test_a_male_patient_can_still_record_is_pregnant_false(): void
    {
        // The validator only blocks is_pregnant=TRUE for non-female patients
        // — false is never a meaningful clinical claim, so it's allowed
        // through unchanged.
        $patient = Patient::factory()->create(['gender' => 'male']);

        $response = $this->actingAs($patient->user, 'sanctum')
            ->putJson('/api/patient/medical-record/pregnancy', ['is_pregnant' => false]);

        $response->assertStatus(200);
    }
}
