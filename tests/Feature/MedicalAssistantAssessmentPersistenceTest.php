<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Conversation;
use App\Models\DoctorSummary;
use App\Models\Patient;
use App\Models\Specialization;
use App\Services\MedicalAssistant\MedicalAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A finished interview must, in the same flow: persist an Assessment with
 * status=completed + a resolved specialty_id/code/name_ar, AND turn the
 * (previously unused) doctor_summaries table into the real medical report
 * for the doctor -- filled straight from the AI service's deterministic
 * medical_report.content, doctor_id left null until a booking links it.
 */
class MedicalAssistantAssessmentPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function fakePythonResponses(): void
    {
        config(['services.medical_assistant.url' => 'http://127.0.0.1:8000']);

        Http::fake([
            'http://127.0.0.1:8000/api/interview/turn' => Http::response([
                'session_id' => 'sess-persist-1',
                'finished' => true,
                'question' => null,
                'symptoms' => [],
                'emergency_detected' => false,
                'risk_level' => 'none',
            ]),
            'http://127.0.0.1:8000/api/assessment/run' => Http::response([
                'urgency' => ['level' => 'URGENT', 'score' => 0.8, 'explanation' => 'x'],
                'specialty' => ['specialty' => 'Pulmonology', 'confidence' => 0.9, 'explanation' => 'x'],
                'confidence' => ['overall_confidence' => 0.8, 'requires_human_review' => false, 'explanation' => 'x'],
                'explanation' => [
                    'summary' => 'ملخّص', 'medical_reasoning' => 'سبب', 'recommendation' => 'توصية', 'disclaimer' => 'تنويه',
                ],
                'predictions' => ['predictions' => [['disease' => 'Bronchitis', 'score' => 0.7, 'explanation' => 'x']]],
                'patient_summary' => [
                    'summary_ar' => 'ملخّص',
                    'possible_condition' => ['name' => 'Bronchitis', 'confidence' => 70.0],
                    'urgency' => ['level' => 'URGENT', 'label_ar' => 'عاجل'],
                    'specialty' => ['code' => 'pulmonology', 'name_ar' => 'أمراض الصدر والجهاز التنفسي'],
                    'symptoms' => ['سعال'],
                    'disclaimer_ar' => 'تنويه',
                ],
                'medical_report' => [
                    'content' => "تقرير طبي مختصر\nالشكوى الرئيسية\nسعال",
                    'missing_fields' => ['age'],
                ],
            ]),
        ]);
    }

    public function test_finished_interview_persists_assessment_with_resolved_specialty(): void
    {
        $this->fakePythonResponses();
        // Seeded by migration 2026_08_17_000002 on every fresh database.
        Specialization::where('name', 'Pulmonology')->firstOrFail();

        $patient = Patient::factory()->create();
        $conversation = Conversation::create(['patient_id' => $patient->id, 'title' => 'محادثة اختبار', 'status' => 'active']);

        app(MedicalAssistantService::class)->handleTextMessage($conversation, 'عندي سعال');

        $assessment = Assessment::where('conversation_id', $conversation->id)->first();

        $this->assertNotNull($assessment);
        $this->assertSame(Assessment::STATUS_COMPLETED, $assessment->status);
        $this->assertSame('Pulmonology', $assessment->recommended_specialty);
        $this->assertSame('pulmonology', $assessment->specialty_code);
        $this->assertSame('أمراض الصدر والجهاز التنفسي', $assessment->specialty_name_ar);
        $this->assertNotNull($assessment->specialty_id);
    }

    public function test_finished_interview_creates_a_doctor_summary_from_medical_report_content(): void
    {
        $this->fakePythonResponses();

        $patient = Patient::factory()->create();
        $conversation = Conversation::create(['patient_id' => $patient->id, 'title' => 'محادثة اختبار', 'status' => 'active']);

        app(MedicalAssistantService::class)->handleTextMessage($conversation, 'عندي سعال');

        $summary = DoctorSummary::where('conversation_id', $conversation->id)->first();

        $this->assertNotNull($summary);
        $this->assertSame("تقرير طبي مختصر\nالشكوى الرئيسية\nسعال", $summary->summary);
        $this->assertSame($patient->id, $summary->patient_id);
        $this->assertNull($summary->doctor_id, 'doctor_id must stay null until a consultation is booked');
        $this->assertSame(DoctorSummary::STATUS_DRAFT, $summary->status);
    }

    public function test_no_doctor_summary_is_created_when_ai_service_omits_medical_report(): void
    {
        config(['services.medical_assistant.url' => 'http://127.0.0.1:8000']);
        Http::fake([
            'http://127.0.0.1:8000/api/interview/turn' => Http::response([
                'session_id' => 'sess-persist-2', 'finished' => true, 'symptoms' => [],
            ]),
            'http://127.0.0.1:8000/api/assessment/run' => Http::response([
                'urgency' => ['level' => 'NON_URGENT', 'score' => 0.1, 'explanation' => 'x'],
                'specialty' => ['specialty' => 'General Medicine', 'confidence' => 0.5, 'explanation' => 'x'],
                'confidence' => ['overall_confidence' => 0.5, 'requires_human_review' => false, 'explanation' => 'x'],
                'explanation' => ['summary' => 's', 'medical_reasoning' => 'm', 'recommendation' => 'r', 'disclaimer' => 'd'],
                'predictions' => ['predictions' => []],
                // Older-AI-service simulation: no patient_summary/medical_report at all.
            ]),
        ]);

        $patient = Patient::factory()->create();
        $conversation = Conversation::create(['patient_id' => $patient->id, 'title' => 'محادثة اختبار', 'status' => 'active']);

        app(MedicalAssistantService::class)->handleTextMessage($conversation, 'رسالة');

        $this->assertNotNull(Assessment::where('conversation_id', $conversation->id)->first());
        $this->assertNull(DoctorSummary::where('conversation_id', $conversation->id)->first());
    }
}
