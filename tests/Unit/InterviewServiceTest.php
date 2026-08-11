<?php

namespace Tests\Unit;

use App\Services\Interview\InterviewService;
use App\Services\MedicalAssistant\MedicalAssistantClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Patch P1: InterviewService::sendMessage() must forward the AI service's
 * safety-layer fields (emergency_detected/risk_level/red_flags/
 * recommended_action) instead of silently dropping them from its return
 * array — the bug found by the Laravel integration audit.
 */
class InterviewServiceTest extends TestCase
{
    private function service(): InterviewService
    {
        config(['services.medical_assistant.url' => 'http://127.0.0.1:8000']);

        return new InterviewService(new MedicalAssistantClient());
    }

    public function test_it_forwards_the_safety_layer_fields_from_the_ai_response(): void
    {
        Http::fake([
            'http://127.0.0.1:8000/api/interview/turn' => Http::response([
                'session_id' => 'sess-1',
                'finished' => true,
                'question' => null,
                'symptoms' => [],
                'emergency_detected' => true,
                'risk_level' => 'immediate',
                'red_flags' => [[
                    'rule_id' => 'HEALIX_REDFLAG_0001',
                    'name_ar' => 'نمط متلازمة الشريان التاجي الحادة',
                    'name_en' => 'Acute Coronary Syndrome pattern',
                    'risk_level' => 'immediate',
                    'evidence' => 'chest pain',
                ]],
                'recommended_action' => 'Seek emergency care immediately.',
            ]),
        ]);

        $result = $this->service()->sendMessage('chest pain and shortness of breath', null);

        $this->assertTrue($result['emergency_detected']);
        $this->assertSame('immediate', $result['risk_level']);
        $this->assertCount(1, $result['red_flags']);
        $this->assertSame('HEALIX_REDFLAG_0001', $result['red_flags'][0]['rule_id']);
        $this->assertSame('Seek emergency care immediately.', $result['recommended_action']);
    }

    public function test_missing_safety_fields_default_safely_instead_of_being_fabricated(): void
    {
        // An AI response shape without the safety layer at all (older build,
        // or a schema change) must not throw and must not invent
        // emergency_detected=true out of nowhere.
        Http::fake([
            'http://127.0.0.1:8000/api/interview/turn' => Http::response([
                'session_id' => 'sess-2',
                'finished' => false,
                'question' => 'Since when?',
                'symptoms' => [],
            ]),
        ]);

        $result = $this->service()->sendMessage('mild headache', null);

        $this->assertFalse($result['emergency_detected']);
        $this->assertSame('none', $result['risk_level']);
        $this->assertSame([], $result['red_flags']);
        $this->assertNull($result['recommended_action']);
    }

    public function test_existing_contract_fields_are_unaffected(): void
    {
        // Regression: adding the safety fields must not change any
        // previously-existing return field's value or type.
        Http::fake([
            'http://127.0.0.1:8000/api/interview/turn' => Http::response([
                'session_id' => 'sess-3',
                'finished' => false,
                'question' => 'How long has this lasted?',
                'next_slot' => 'onset',
                'symptoms' => [['text' => 'headache', 'negated' => false, 'confidence' => 0.9]],
                'emergency_detected' => false,
                'risk_level' => 'none',
            ]),
        ]);

        $result = $this->service()->sendMessage('headache', 'sess-3');

        $this->assertSame('sess-3', $result['session_id']);
        $this->assertFalse($result['finished']);
        $this->assertSame('How long has this lasted?', $result['question']);
        $this->assertSame('onset', $result['next_slot']);
        $this->assertSame([['text' => 'headache', 'negated' => false, 'confidence' => 0.9]], $result['symptoms']);
    }
}
