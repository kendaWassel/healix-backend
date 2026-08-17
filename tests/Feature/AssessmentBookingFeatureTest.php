<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Conversation;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Specialization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /patient/assessments/{id}/booking — the unified assessment-result +
 * specialty + available-doctors + slots endpoint for the booking screen.
 */
class AssessmentBookingFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function assessmentFor(Patient $patient, string $specialtyName = 'Pulmonology'): Assessment
    {
        $conversation = Conversation::create([
            'patient_id' => $patient->id,
            'title' => 'محادثة اختبار',
            'status' => 'completed',
        ]);

        return Assessment::create([
            'conversation_id' => $conversation->id,
            'status' => Assessment::STATUS_COMPLETED,
            'triage' => 'Medium',
            'recommended_specialty' => $specialtyName,
            'possible_diseases' => [['disease' => 'Bronchitis', 'score' => 0.7]],
            'extracted_symptoms' => [['text' => 'سعال', 'negated' => false, 'confidence' => 0.9]],
            'emergency_detected' => false,
        ]);
    }

    public function test_returns_assessment_specialty_and_doctors_with_slots(): void
    {
        $patient = Patient::factory()->create();
        $this->actingAs($patient->user);

        // "Pulmonology" is seeded by migration 2026_08_17_000002 on every
        // fresh database, so no manual Specialization::create() is needed.
        $specialization = Specialization::where('name', 'Pulmonology')->firstOrFail();
        Doctor::factory()->create([
            'specialization_id' => $specialization->id,
            'from' => '09:00:00',
            'to' => '17:00:00',
        ]);

        $assessment = $this->assessmentFor($patient, 'Pulmonology');

        $response = $this->getJson("/api/patient/assessments/{$assessment->id}/booking");

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.assessment.id', $assessment->id);
        $response->assertJsonPath('data.specialty.code', 'pulmonology');
        $response->assertJsonPath('data.can_book', true);
        $this->assertNotEmpty($response->json('data.doctors'));
        $this->assertNotEmpty($response->json('data.doctors.0.available_slots'));
    }

    public function test_can_book_is_false_when_no_doctor_exists_for_the_specialty(): void
    {
        $patient = Patient::factory()->create();
        $this->actingAs($patient->user);

        // "Rheumatology" exists (seeded) but no doctor is created for it.
        $assessment = $this->assessmentFor($patient, 'Rheumatology');

        $response = $this->getJson("/api/patient/assessments/{$assessment->id}/booking");

        $response->assertOk();
        $response->assertJsonPath('data.can_book', false);
        $response->assertJsonPath('data.doctors', []);
    }

    public function test_returns_403_for_another_patients_assessment(): void
    {
        $owner = Patient::factory()->create();
        $intruder = Patient::factory()->create();
        $this->actingAs($intruder->user);

        $assessment = $this->assessmentFor($owner);

        $response = $this->getJson("/api/patient/assessments/{$assessment->id}/booking");

        $response->assertStatus(403);
    }

    public function test_returns_404_for_missing_assessment(): void
    {
        $patient = Patient::factory()->create();
        $this->actingAs($patient->user);

        $response = $this->getJson('/api/patient/assessments/999999/booking');

        $response->assertStatus(404);
    }
}
