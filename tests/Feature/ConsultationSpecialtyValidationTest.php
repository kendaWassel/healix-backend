<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Conversation;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Specialization;
use App\Services\GoogleMeetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * ConsultationService::bookConsultation's specialty-match validation: a
 * booking whose doctor doesn't match Assessment.recommended_specialty
 * (via specialty_id, the resolved value -- never the free-text
 * recommended_specialty string) is rejected outright, not silently
 * allowed. Only applies when assessment_id is actually supplied AND that
 * assessment resolved to a real specialty_id -- both are optional, and
 * neither having a value means there's nothing real to validate against.
 */
class ConsultationSpecialtyValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Neutralizes ConsultationService's WhatsApp/SMS notification calls
        // (UltraMsgService/TraccarSmsService, both real Http-facade calls) --
        // this test only cares about the specialty-match validation's own
        // status code, not notification delivery.
        Http::fake();

        // GoogleMeetService's real constructor eagerly loads
        // storage/app/google/google-service-account.json, which doesn't
        // exist in this test environment -- it throws the instant the
        // container tries to build ConsultationService at all, before this
        // test's own validation even runs (a pre-existing gap, confirmed
        // present in ConsultationFeatureTest/ConsultationLinksAssessmentTest
        // too). Mockery::mock() never invokes the real constructor, so
        // binding this replaces the real service without touching that gap.
        $this->app->instance(
            GoogleMeetService::class,
            Mockery::mock(GoogleMeetService::class)->shouldIgnoreMissing()
        );
    }

    private function patientWithToken(): Patient
    {
        $patient = Patient::factory()->create();
        $this->actingAs($patient->user);
        return $patient;
    }

    private function assessmentWithSpecialty(Patient $patient, ?Specialization $specialization): Assessment
    {
        $conversation = Conversation::create([
            'patient_id' => $patient->id,
            'title' => 'Specialty validation test',
            'status' => 'completed',
        ]);

        return Assessment::create([
            'conversation_id' => $conversation->id,
            'status' => Assessment::STATUS_COMPLETED,
            'recommended_specialty' => $specialization?->name_ar ?? 'استشر طبيب أو راجع أقرب مركز رعاية أولية',
            'specialty_id' => $specialization?->id,
            'specialty_code' => $specialization?->code,
            'specialty_name_ar' => $specialization?->name_ar,
        ]);
    }

    public function test_booking_a_doctor_outside_the_recommended_specialty_is_rejected(): void
    {
        $patient = $this->patientWithToken();
        $neurology = Specialization::create(['name' => 'Neurology', 'name_ar' => 'الأمراض العصبية', 'code' => 'neurology']);
        $cardiology = Specialization::create(['name' => 'Cardiology', 'name_ar' => 'أمراض القلب', 'code' => 'cardiology']);

        $assessment = $this->assessmentWithSpecialty($patient, $neurology);
        $wrongDoctor = Doctor::factory()->create([
            'specialization_id' => $cardiology->id,
            'from' => '09:00:00',
            'to' => '17:00:00',
        ]);

        $response = $this->postJson('/api/patient/consultations/book', [
            'doctor_id' => $wrongDoctor->id,
            'call_type' => 'schedule',
            'scheduled_at' => now()->addDay()->setTime(10, 0)->toDateTimeString(),
            'assessment_id' => $assessment->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('consultations', ['assessment_id' => $assessment->id]);
    }

    public function test_booking_a_doctor_matching_the_recommended_specialty_succeeds(): void
    {
        $patient = $this->patientWithToken();
        $neurology = Specialization::create(['name' => 'Neurology', 'name_ar' => 'الأمراض العصبية', 'code' => 'neurology']);

        $assessment = $this->assessmentWithSpecialty($patient, $neurology);
        $rightDoctor = Doctor::factory()->create([
            'specialization_id' => $neurology->id,
            'from' => '09:00:00',
            'to' => '17:00:00',
        ]);

        $response = $this->postJson('/api/patient/consultations/book', [
            'doctor_id' => $rightDoctor->id,
            'call_type' => 'schedule',
            'scheduled_at' => now()->addDay()->setTime(10, 0)->toDateTimeString(),
            'assessment_id' => $assessment->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('consultations', [
            'assessment_id' => $assessment->id,
            'doctor_id' => $rightDoctor->id,
        ]);
    }

    /**
     * An assessment with no resolved specialty_id (e.g. a "طب عام" /
     * general-practice recommendation, which has no matching Laravel
     * specialty by design -- CLAUDE.md's own Known limitations, Python
     * repo) has nothing real to validate against -- booking must proceed,
     * not be blocked by a comparison that isn't meaningful.
     */
    public function test_booking_with_an_unresolved_assessment_specialty_is_not_blocked(): void
    {
        $patient = $this->patientWithToken();
        $anyDoctor = Doctor::factory()->create(['from' => '09:00:00', 'to' => '17:00:00']);

        $assessment = $this->assessmentWithSpecialty($patient, null);

        $response = $this->postJson('/api/patient/consultations/book', [
            'doctor_id' => $anyDoctor->id,
            'call_type' => 'schedule',
            'scheduled_at' => now()->addDay()->setTime(10, 0)->toDateTimeString(),
            'assessment_id' => $assessment->id,
        ]);

        $response->assertStatus(201);
    }

    /**
     * The plain direct-booking flow (no AI assessment involved at all --
     * BookConsultationRequest's own comment) must keep working unchanged:
     * no assessment_id means nothing to validate.
     */
    public function test_booking_without_an_assessment_id_is_not_blocked(): void
    {
        $this->patientWithToken();
        $anyDoctor = Doctor::factory()->create(['from' => '09:00:00', 'to' => '17:00:00']);

        $response = $this->postJson('/api/patient/consultations/book', [
            'doctor_id' => $anyDoctor->id,
            'call_type' => 'schedule',
            'scheduled_at' => now()->addDay()->setTime(10, 0)->toDateTimeString(),
        ]);

        $response->assertStatus(201);
    }
}
