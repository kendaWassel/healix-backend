<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * White Box: RatingController::rateDoctor()'s real internal logic — never
 * genuinely exercised before this file. The pre-existing RatingTest.php
 * had two tests that looked like coverage but weren't: one posted to a
 * route that doesn't exist (POST /api/patient/ratings with a body —
 * the real route is POST /consultations/{id}/rate/{doctorId}, path
 * params only), and the other computed Rating::avg('stars') directly in
 * the test itself and asserted it against its own input — verifying
 * SQL's AVG(), not one line of RatingController's actual code. Both are
 * left untouched (out of this review's scope to edit without separate
 * approval); this file adds real coverage of the same internal logic.
 */
class RatingLogicTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_rating_avg_is_recomputed_from_real_ratings_after_a_new_one_is_submitted(): void
    {
        $doctor = Doctor::factory()->create(['rating_avg' => 0]);

        $patientA = Patient::factory()->create();
        $consultationA = Consultation::create([
            'patient_id' => $patientA->id, 'doctor_id' => $doctor->id, 'status' => 'completed',
        ]);
        $this->actingAs($patientA->user, 'sanctum')
            ->postJson("/api/patient/consultations/{$consultationA->id}/rate/{$doctor->id}", ['stars' => 4])
            ->assertStatus(200);

        $patientB = Patient::factory()->create();
        $consultationB = Consultation::create([
            'patient_id' => $patientB->id, 'doctor_id' => $doctor->id, 'status' => 'completed',
        ]);
        $this->actingAs($patientB->user, 'sanctum')
            ->postJson("/api/patient/consultations/{$consultationB->id}/rate/{$doctor->id}", ['stars' => 5])
            ->assertStatus(200);

        // Real internal logic verified: RatingController::rateDoctor() recomputes
        // round(Rating::where(target_type=doctor, target_id=$doctor)->avg('stars'), 1)
        // and persists it onto doctors.rating_avg — (4+5)/2 = 4.5.
        $this->assertEquals(4.5, $doctor->fresh()->rating_avg);
    }

    public function test_rating_a_doctor_is_rejected_without_a_completed_consultation_between_them(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        // No Consultation at all between this patient and this doctor.

        $response = $this->actingAs($patient->user, 'sanctum')
            ->postJson("/api/patient/consultations/999999/rate/{$doctor->id}", ['stars' => 5]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('ratings', ['user_id' => $patient->user->id, 'target_id' => $doctor->id]);
    }
}
