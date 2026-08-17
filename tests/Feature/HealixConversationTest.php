<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Locks in POST /api/patient/conversations/{id}/healix-messages — the
 * Healix AI triage backend, a genuinely different integration from
 * MedicalAssistantChatIntegrationTest's own /messages endpoint (see
 * HealixConversationService's module docstring for why these are two
 * separate pipelines, not one branching endpoint). The Python service
 * itself is faked at the HTTP boundary (Http::fake), same approach
 * MedicalAssistantChatIntegrationTest already uses for its own AI
 * service — this guards the Laravel-side contract, not the Python
 * model's behavior.
 */
class HealixConversationTest extends TestCase
{
    use RefreshDatabase;

    private function patientUser(): User
    {
        $user = User::create([
            'full_name' => 'Healix Chat Patient',
            'email' => 'healix-patient-' . uniqid() . '@example.com',
            'phone' => '09' . random_int(10000000, 99999999),
            'role' => 'patient',
            'password' => 'password123',
            'status' => 'approved',
            'is_active' => true,
        ]);
        $user->markEmailAsVerified();

        Patient::create(['user_id' => $user->id, 'gender' => 'female']);

        return $user->fresh();
    }

    private function fakeHealixChat(array $overrides = []): void
    {
        Http::fake([
            '*/chat' => Http::response(array_merge([
                'thread_id' => '1',
                'reply' => 'بناءً على الأعراض يلي ذكرتها، في احتمال أولي واحد بس مش تشخيص نهائي.',
                'stage' => 'diagnosis',
                'is_crisis' => false,
                'severity' => null,
                'red_flags' => [],
                'diagnosis' => ['status' => 'differential', 'differential' => [], 'reasoning' => null],
                'specialty' => 'عصبية',
                'reports' => ['patient' => 'fake patient report', 'doctor' => 'fake doctor report'],
            ], $overrides)),
        ]);
    }

    public function test_a_healix_turn_persists_both_messages_and_returns_the_right_shape(): void
    {
        $user = $this->patientUser();
        $this->fakeHealixChat();

        $conversationId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/patient/conversations', ['title' => 'Symptom Check'])
            ->json('data.id');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/patient/conversations/{$conversationId}/healix-messages", [
                'message' => 'عندي صداع نابض من جهة وحدة مع غثيان',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('available', true)
            ->assertJsonPath('stage', 'diagnosis')
            ->assertJsonPath('is_crisis', false)
            ->assertJsonPath('severity', null)
            ->assertJsonPath('red_flags', [])
            ->assertJsonPath('diagnosis.status', 'differential')
            ->assertJsonPath('specialty', 'عصبية')
            ->assertJsonPath('reply', 'بناءً على الأعراض يلي ذكرتها، في احتمال أولي واحد بس مش تشخيص نهائي.')
            ->assertJsonPath('reports.patient', 'fake patient report')
            ->assertJsonPath('data.patient_message.sender', 'patient')
            ->assertJsonPath('data.patient_message.message', 'عندي صداع نابض من جهة وحدة مع غثيان')
            ->assertJsonPath('data.assistant_message.sender', 'assistant');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversationId,
            'sender' => 'patient',
            'message' => 'عندي صداع نابض من جهة وحدة مع غثيان',
        ]);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversationId,
            'sender' => 'assistant',
            'message' => 'بناءً على الأعراض يلي ذكرتها، في احتمال أولي واحد بس مش تشخيص نهائي.',
        ]);

        // thread_id sent to Python is the conversation's own id, not
        // session_id (that field belongs to the OTHER assistant) —
        // verifies the actual wiring, not just the response shape.
        Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($conversationId) {
            return str_contains($request->url(), '/chat')
                && $request['thread_id'] === (string) $conversationId;
        });
    }

    /**
     * is_crisis/severity/red_flags/diagnosis were received from the Python
     * response but silently dropped before reaching the frontend — this
     * pins down that they now actually surface, specifically on the
     * crisis path (the one CLAUDE.md on the Python side calls out as
     * safety-critical: "duplicates what stage == 'crisis' already
     * implies... deserves a signal Laravel can check directly").
     */
    public function test_a_crisis_turn_surfaces_is_crisis_and_severity_to_the_frontend(): void
    {
        $user = $this->patientUser();
        $this->fakeHealixChat([
            'stage' => 'crisis',
            'reply' => 'فهمتك، وهاد الشي خارج قدرتي — بس في ناس فعلاً بيقدروا يساعدوك هلق.',
            'is_crisis' => true,
            'severity' => null,
            'red_flags' => [],
            'diagnosis' => null,
            'specialty' => null,
            'reports' => null,
        ]);

        $conversationId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/patient/conversations', ['title' => 'Crisis Check'])
            ->json('data.id');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/patient/conversations/{$conversationId}/healix-messages", [
                'message' => 'بدي موت، ما في فايدة',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('available', true)
            ->assertJsonPath('stage', 'crisis')
            ->assertJsonPath('is_crisis', true)
            ->assertJsonPath('specialty', null)
            ->assertJsonPath('diagnosis', null);
    }

    /**
     * A red-flag (emergency) turn, not crisis — the other safety-terminal
     * path, and the one that actually carries structured red_flags/severity
     * content worth asserting on.
     */
    public function test_an_emergency_turn_surfaces_severity_and_red_flags_to_the_frontend(): void
    {
        $user = $this->patientUser();
        $this->fakeHealixChat([
            'stage' => 'emergency',
            'reply' => 'الأعراض يلي ذكرتها بتستدعي تدخل طبي إسعافي فورًا.',
            'is_crisis' => false,
            'severity' => 'emergency',
            'red_flags' => ['acs_chest_pain', 'llm'],
            'diagnosis' => null,
            'specialty' => null,
            'reports' => null,
        ]);

        $conversationId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/patient/conversations', ['title' => 'Emergency Check'])
            ->json('data.id');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/patient/conversations/{$conversationId}/healix-messages", [
                'message' => 'عندي ألم شديد بصدري وضيق تنفس',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('stage', 'emergency')
            ->assertJsonPath('is_crisis', false)
            ->assertJsonPath('severity', 'emergency')
            ->assertJsonPath('red_flags', ['acs_chest_pain', 'llm']);
    }

    public function test_healix_service_unavailable_degrades_gracefully_not_a_raw_exception(): void
    {
        $user = $this->patientUser();

        // Simulates the Python service being down: every attempt gets a
        // 500, exhausting FastApiClient's retries and surfacing as
        // AIServiceException — which HealixConversationService must catch,
        // not let bubble up as an unhandled 500 to the patient.
        Http::fake([
            '*/chat' => Http::response(['detail' => 'internal server error'], 500),
        ]);

        $conversationId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/patient/conversations', ['title' => 'Symptom Check'])
            ->json('data.id');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/patient/conversations/{$conversationId}/healix-messages", [
                'message' => 'عندي صداع من يومين',
            ]);

        // Still a clean, successful HTTP response -- the outage shows up
        // in the CONTENT (available=false, stage=null, a plain notice),
        // never as a 500 with a leaked exception/provider error message.
        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('available', false)
            ->assertJsonPath('stage', null)
            // Neutral defaults, not a real safety verdict — the turn never
            // reached Python at all (HealixConversationService's own
            // comment on this branch). A frontend must check 'available'
            // before trusting these, which is exactly what this pins down:
            // false/null/[] here, not something that looks like a
            // completed, clear screening.
            ->assertJsonPath('is_crisis', false)
            ->assertJsonPath('severity', null)
            ->assertJsonPath('red_flags', [])
            ->assertJsonPath('diagnosis', null)
            ->assertJsonPath('specialty', null)
            ->assertJsonPath('reply', __('ai.healix_unavailable_notice'))
            ->assertJsonPath('data.assistant_message.message', __('ai.healix_unavailable_notice'));

        $content = $response->getContent();
        $this->assertStringNotContainsString('internal server error', $content);
        $this->assertStringNotContainsString('Exception', $content);
        $this->assertStringNotContainsString('AIService', $content);

        // The patient's own message is never lost even though the AI
        // call failed -- same "never roll back what's already committed"
        // guarantee MedicalAssistantService's own outage handling has.
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversationId,
            'sender' => 'patient',
            'message' => 'عندي صداع من يومين',
        ]);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversationId,
            'sender' => 'assistant',
            'message' => __('ai.healix_unavailable_notice'),
        ]);
    }

    public function test_a_patient_cannot_send_a_healix_message_on_another_patients_conversation(): void
    {
        $owner = $this->patientUser();
        $intruder = $this->patientUser();
        $this->fakeHealixChat();

        $conversationId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/patient/conversations', ['title' => 'Private'])
            ->json('data.id');

        $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/patient/conversations/{$conversationId}/healix-messages", ['message' => 'hi'])
            ->assertStatus(403);

        $this->assertDatabaseMissing('messages', ['conversation_id' => $conversationId]);
    }
}
