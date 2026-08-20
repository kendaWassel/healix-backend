<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
     * FastApiClient::send() logs both the outgoing request and the
     * incoming response — by default with the full payload/body, which
     * for Healix means the patient's raw Arabic message and the full
     * doctor/patient report text. HealixAiClient overrides
     * redactPayloadForLogging()/redactResponseBodyForLogging() to strip
     * both down to thread_id only before either reaches the log sink.
     * Asserted here at the real HTTP-route level (not a client-level
     * unit test) so this proves the redaction actually fires on the path
     * a real patient turn takes, not just that the override method
     * exists.
     */
    public function test_healix_request_and_response_logging_redacts_patient_content(): void
    {
        Log::spy();

        $user = $this->patientUser();
        $this->fakeHealixChat();

        $conversationId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/patient/conversations', ['title' => 'Symptom Check'])
            ->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/patient/conversations/{$conversationId}/healix-messages", [
                'message' => 'عندي صداع نابض من جهة وحدة مع غثيان',
            ]);

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context) use ($conversationId) {
                return $message === 'Healix AI triage service request'
                    && $context['payload'] === ['thread_id' => (string) $conversationId]
                    && ! str_contains(json_encode($context), 'صداع');
            })
            ->once();

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context) {
                return $message === 'Healix AI triage service response'
                    && $context['status'] === 200
                    && is_int($context['latency_ms'])
                    && $context['body'] === ['thread_id' => '1']
                    && ! str_contains(json_encode($context), 'تشخيص')
                    && ! str_contains(json_encode($context), 'fake patient report')
                    && ! str_contains(json_encode($context), 'fake doctor report');
            })
            ->once();
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

    /**
     * Previously, diagnosis/specialty/reports/is_crisis/severity were only
     * ever present in the live POST response — never persisted, so
     * GET .../messages (reopening a finished conversation later) always
     * came back with none of it. This locks in that the assistant Message
     * row itself now carries these fields, and that MessageResource
     * actually serializes them on the read path, not just at creation.
     */
    public function test_diagnosis_fields_are_persisted_and_readable_from_conversation_history(): void
    {
        $user = $this->patientUser();
        $this->fakeHealixChat();

        $conversationId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/patient/conversations', ['title' => 'Symptom Check'])
            ->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/patient/conversations/{$conversationId}/healix-messages", [
                'message' => 'عندي صداع نابض من جهة وحدة مع غثيان',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversationId,
            'sender' => 'assistant',
            'is_crisis' => false,
            'severity' => null,
            'specialty' => 'عصبية',
        ]);

        // The read path — a fresh request, as if the patient closed the
        // app and reopened this conversation later.
        $messages = $this->actingAs($user, 'sanctum')
            ->getJson("/api/patient/conversations/{$conversationId}/messages")
            ->assertStatus(200)
            ->json('data.data');

        $assistantMessage = collect($messages)->firstWhere('sender', 'assistant');
        $this->assertNotNull($assistantMessage, 'assistant message missing from history');
        $this->assertSame('عصبية', $assistantMessage['specialty']);
        $this->assertSame('differential', $assistantMessage['diagnosis']['status']);
        $this->assertSame('fake patient report', $assistantMessage['reports']['patient']);
        $this->assertFalse($assistantMessage['is_crisis']);
    }

    /**
     * Mirrors the frontend's own "finished" condition (stage === 'diagnosis'
     * || isEmergency) so a reopened conversation's ended_at agrees with
     * what the patient saw live — previously ended_at was only ever set by
     * the OTHER assistant's service, never by this one, so it stayed null
     * forever for every Healix conversation regardless of outcome.
     */
    public function test_reaching_diagnosis_stage_marks_the_conversation_ended(): void
    {
        $user = $this->patientUser();
        $this->fakeHealixChat(['stage' => 'diagnosis']);

        $conversationId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/patient/conversations', ['title' => 'Symptom Check'])
            ->json('data.id');

        $this->assertDatabaseHas('conversations', ['id' => $conversationId, 'ended_at' => null]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/patient/conversations/{$conversationId}/healix-messages", [
                'message' => 'عندي صداع نابض من جهة وحدة مع غثيان',
            ])
            ->assertStatus(201);

        $this->assertDatabaseMissing('conversations', ['id' => $conversationId, 'ended_at' => null]);
    }

    public function test_a_crisis_turn_also_marks_the_conversation_ended_even_without_a_diagnosis_stage(): void
    {
        $user = $this->patientUser();
        $this->fakeHealixChat(['stage' => 'crisis', 'is_crisis' => true, 'diagnosis' => null, 'specialty' => null]);

        $conversationId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/patient/conversations', ['title' => 'Symptom Check'])
            ->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/patient/conversations/{$conversationId}/healix-messages", [
                'message' => 'بدي أموت نفسي',
            ])
            ->assertStatus(201);

        $this->assertDatabaseMissing('conversations', ['id' => $conversationId, 'ended_at' => null]);
    }

    public function test_a_non_terminal_turn_leaves_the_conversation_open(): void
    {
        $user = $this->patientUser();
        $this->fakeHealixChat(['stage' => 'gathering_history', 'diagnosis' => null, 'specialty' => null]);

        $conversationId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/patient/conversations', ['title' => 'Symptom Check'])
            ->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/patient/conversations/{$conversationId}/healix-messages", [
                'message' => 'عندي صداع',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('conversations', ['id' => $conversationId, 'ended_at' => null]);
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
