<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Conversation;
use App\Models\Doctor;
use App\Models\DoctorSummary;
use App\Models\Patient;
use App\Services\ConsultationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Booking with a conversation_id/assessment_id (the "book with recommended
 * specialty" flow) must link the Consultation back to them, flip the
 * Assessment to STATUS_BOOKED, and attach the chosen doctor to its
 * (previously doctor-less) medical report.
 */
class ConsultationLinksAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_with_assessment_id_links_and_marks_booked(): void
    {
        Notification::fake();

        $doctor = Doctor::factory()->create(['from' => '09:00:00', 'to' => '17:00:00']);
        $patient = Patient::factory()->create();
        $this->actingAs($patient->user);

        $conversation = Conversation::create(['patient_id' => $patient->id, 'title' => 'محادثة اختبار', 'status' => 'completed']);
        $assessment = Assessment::create([
            'conversation_id' => $conversation->id,
            'status' => Assessment::STATUS_COMPLETED,
            'triage' => 'Medium',
            'recommended_specialty' => 'General Medicine',
            'possible_diseases' => [],
            'extracted_symptoms' => [],
            'emergency_detected' => false,
        ]);
        $summary = DoctorSummary::create([
            'conversation_id' => $conversation->id,
            'assessment_id' => $assessment->id,
            'patient_id' => $patient->id,
            'summary' => 'تقرير',
            'status' => DoctorSummary::STATUS_DRAFT,
        ]);

        $validated = [
            'doctor_id' => $doctor->id,
            'call_type' => 'schedule',
            'scheduled_at' => Carbon::now()->addDay()->setTime(10, 0)->toDateTimeString(),
            'conversation_id' => $conversation->id,
            'assessment_id' => $assessment->id,
        ];

        $consultation = app(ConsultationService::class)->bookConsultation($validated);

        $this->assertSame($conversation->id, $consultation->conversation_id);
        $this->assertSame($assessment->id, $consultation->assessment_id);

        $assessment->refresh();
        $this->assertSame(Assessment::STATUS_BOOKED, $assessment->status);

        $summary->refresh();
        $this->assertSame($doctor->id, $summary->doctor_id);
    }

    public function test_booking_without_assessment_id_behaves_exactly_as_before(): void
    {
        Notification::fake();

        $doctor = Doctor::factory()->create(['from' => '09:00:00', 'to' => '17:00:00']);
        $patient = Patient::factory()->create();
        $this->actingAs($patient->user);

        $validated = [
            'doctor_id' => $doctor->id,
            'call_type' => 'schedule',
            'scheduled_at' => Carbon::now()->addDay()->setTime(11, 0)->toDateTimeString(),
        ];

        $consultation = app(ConsultationService::class)->bookConsultation($validated);

        $this->assertNull($consultation->conversation_id);
        $this->assertNull($consultation->assessment_id);
        $this->assertSame('pending', $consultation->status);
    }
}
