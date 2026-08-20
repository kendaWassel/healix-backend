<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Consultation;
use App\Models\Conversation;
use App\Models\Doctor;
use App\Models\DoctorSummary;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /doctor/patients/{patient_id}/doctor-summaries — the first real,
 * routed way for a doctor to see a Healix/AI-generated report.
 * DoctorSummaryPolicy::view is a real $this->authorize() call, not just
 * this route's own role:doctor gate, and uses the same Consultation
 * relationship rule as MedicalRecordPolicy::view's own fix.
 */
class DoctorSummaryControllerTest extends TestCase
{
    use RefreshDatabase;

    private function conversationFor(Patient $patient): Conversation
    {
        return Conversation::create([
            'patient_id' => $patient->id,
            'title' => 'DoctorSummary controller test',
            'status' => 'completed',
        ]);
    }

    public function test_doctor_with_a_real_consultation_can_view_the_patients_doctor_summary(): void
    {
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        $conversation = $this->conversationFor($patient);
        $assessment = Assessment::create([
            'conversation_id' => $conversation->id,
            'status' => Assessment::STATUS_COMPLETED,
            'recommended_specialty' => 'الأمراض العصبية',
        ]);
        $summary = DoctorSummary::create([
            'conversation_id' => $conversation->id,
            'assessment_id' => $assessment->id,
            'patient_id' => $patient->id,
            'summary' => 'ملخص طبي: الشقيقة، مطابقة تامة.',
            'status' => DoctorSummary::STATUS_DRAFT,
        ]);

        Consultation::create(['patient_id' => $patient->id, 'doctor_id' => $doctor->id]);

        $response = $this->actingAs($doctor->user)
            ->getJson("/api/doctor/patients/{$patient->id}/doctor-summaries");

        $response->assertStatus(200)
            ->assertJsonPath('data.patient_id', $patient->id)
            ->assertJsonPath('data.doctor_summaries.0.id', $summary->id)
            ->assertJsonPath('data.doctor_summaries.0.summary', 'ملخص طبي: الشقيقة، مطابقة تامة.');
    }

    public function test_doctor_with_no_consultation_for_the_patient_is_rejected(): void
    {
        $patient = Patient::factory()->create();
        $unrelatedDoctor = Doctor::factory()->create();

        $conversation = $this->conversationFor($patient);
        $assessment = Assessment::create([
            'conversation_id' => $conversation->id,
            'status' => Assessment::STATUS_COMPLETED,
            'recommended_specialty' => 'الأمراض العصبية',
        ]);
        DoctorSummary::create([
            'conversation_id' => $conversation->id,
            'assessment_id' => $assessment->id,
            'patient_id' => $patient->id,
            'summary' => 'ملخص طبي سري.',
            'status' => DoctorSummary::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($unrelatedDoctor->user)
            ->getJson("/api/doctor/patients/{$patient->id}/doctor-summaries");

        $response->assertStatus(403);
    }

    /**
     * A real relationship but no report yet (e.g. the Healix conversation
     * hasn't reached a diagnosis) must still pass authorization -- the
     * controller authorizes against an unsaved DoctorSummary stand-in in
     * this case, same pattern as MedicalRecordController::viewDetails.
     */
    public function test_doctor_with_a_consultation_but_no_report_yet_gets_an_empty_list_not_a_403(): void
    {
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        Consultation::create(['patient_id' => $patient->id, 'doctor_id' => $doctor->id]);

        $response = $this->actingAs($doctor->user)
            ->getJson("/api/doctor/patients/{$patient->id}/doctor-summaries");

        $response->assertStatus(200)
            ->assertJsonPath('data.doctor_summaries', []);
    }

    public function test_a_patient_cannot_access_the_doctor_facing_endpoint_at_all(): void
    {
        $patient = Patient::factory()->create();

        $response = $this->actingAs($patient->user)
            ->getJson("/api/doctor/patients/{$patient->id}/doctor-summaries");

        $response->assertStatus(403);
    }

    public function test_returns_404_for_a_nonexistent_patient(): void
    {
        $doctor = Doctor::factory()->create();

        $response = $this->actingAs($doctor->user)
            ->getJson('/api/doctor/patients/999999/doctor-summaries');

        $response->assertStatus(404);
    }
}
