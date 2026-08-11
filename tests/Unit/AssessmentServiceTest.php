<?php

namespace Tests\Unit;

use App\Services\MedicalAssistant\AssessmentService;
use App\Services\MedicalAssistant\MedicalAssistantClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Patch P3: AssessmentService::run() must send interview_risk to
 * /api/assessment/run when the caller supplies it, and must omit the key
 * entirely (not send a fabricated false/none) when it doesn't — matching
 * AssessmentRequest.interview_risk's Optional[...] = None contract on the
 * Python side exactly.
 */
class AssessmentServiceTest extends TestCase
{
    private function fakeAssessmentResponse(): array
    {
        return [
            'urgency' => ['level' => 'EMERGENCY', 'score' => 1.0, 'explanation' => 'fake'],
            'specialty' => ['specialty' => 'Cardiology', 'confidence' => 0.9, 'explanation' => 'fake'],
            'confidence' => ['overall_confidence' => 0.9, 'requires_human_review' => false, 'explanation' => 'fake'],
            'explanation' => [
                'summary' => 'fake', 'medical_reasoning' => 'fake',
                'recommendation' => 'fake', 'disclaimer' => 'fake',
            ],
        ];
    }

    private function service(): AssessmentService
    {
        config(['services.medical_assistant.url' => 'http://127.0.0.1:8000']);

        return new AssessmentService(new MedicalAssistantClient());
    }

    public function test_interview_risk_is_sent_when_provided(): void
    {
        Http::fake([
            'http://127.0.0.1:8000/api/assessment/run' => Http::response($this->fakeAssessmentResponse()),
        ]);

        $this->service()->run(
            'sess-1',
            ['chest pain and shortness of breath'],
            [['text' => 'chest pain', 'negated' => false, 'confidence' => 0.9]],
            ['emergency_detected' => true, 'risk_level' => 'immediate']
        );

        Http::assertSent(function (Request $request) {
            return $request->url() === 'http://127.0.0.1:8000/api/assessment/run'
                && $request['interview_risk'] === ['emergency_detected' => true, 'risk_level' => 'immediate'];
        });
    }

    public function test_interview_risk_is_omitted_entirely_when_not_provided(): void
    {
        Http::fake([
            'http://127.0.0.1:8000/api/assessment/run' => Http::response($this->fakeAssessmentResponse()),
        ]);

        // No 4th argument -- the pre-Patch-P3 call shape, must still work.
        $this->service()->run('sess-2', ['mild headache'], []);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'http://127.0.0.1:8000/api/assessment/run'
                && ! array_key_exists('interview_risk', $request->data());
        });
    }

    public function test_interview_risk_defaults_are_applied_for_partial_input(): void
    {
        Http::fake([
            'http://127.0.0.1:8000/api/assessment/run' => Http::response($this->fakeAssessmentResponse()),
        ]);

        // Caller supplies the array but omits risk_level -- must default to
        // 'none', never left missing or null inside a sent interview_risk.
        $this->service()->run('sess-3', ['x'], [], ['emergency_detected' => false]);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'http://127.0.0.1:8000/api/assessment/run'
                && $request['interview_risk'] === ['emergency_detected' => false, 'risk_level' => 'none'];
        });
    }

    public function test_existing_payload_fields_are_unaffected(): void
    {
        Http::fake([
            'http://127.0.0.1:8000/api/assessment/run' => Http::response($this->fakeAssessmentResponse()),
        ]);

        $this->service()->run(
            'sess-4',
            ['msg one', 'msg two'],
            [['text' => 'fever', 'negated' => false, 'confidence' => 0.8]]
        );

        Http::assertSent(function (Request $request) {
            return $request['session_id'] === 'sess-4'
                && $request['raw_messages'] === ['msg one', 'msg two']
                && $request['symptoms'] === [['text' => 'fever', 'negated' => false, 'confidence' => 0.8]];
        });
    }
}
