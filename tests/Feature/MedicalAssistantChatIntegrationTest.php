<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Locks in the exact request/response contract the AI_Medical_Assistant.js
 * mobile screen depends on:
 *
 *   POST /api/patient/conversations                        -> { data: { id } }
 *   POST /api/patient/conversations/{id}/messages           -> { question, detected_symptoms, finished }
 *   POST /api/speech-to-text                                -> { success, text, question, detected_symptoms, finished }
 *
 * The Python interview engine is faked — this test only guards the Laravel
 * side of the contract, not the AI model's behavior.
 */
class MedicalAssistantChatIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * email_verified_at is intentionally not mass-assignable on User (see
     * $fillable), so it has to be set explicitly — mass-assigning it here
     * would silently no-op and every request would 403 with "Email not
     * verified" regardless of the route being tested.
     */
    private function patientUser(): User
    {
        $user = User::create([
            'full_name' => 'Chat Patient',
            'email' => 'chat-patient-' . uniqid() . '@example.com',
            'phone' => '09' . random_int(10000000, 99999999),
            'role' => 'patient',
            'password' => 'password123',
            'status' => 'approved',
            'is_active' => true,
        ]);
        $user->markEmailAsVerified();

        Patient::create(['user_id' => $user->id, 'gender' => 'male']);

        return $user->fresh();
    }

    private function fakeInterviewEngine(array $overrides = [], array $assessmentOverrides = []): void
    {
        Http::fake([
            '*/api/interview/turn' => Http::response(array_merge([
                'session_id' => 'sess-123',
                'finished' => false,
                'question' => 'How long have you had this headache?',
                'next_slot' => null,
                'symptoms' => [['text' => 'headache', 'negated' => false, 'confidence' => 0.9]],
                // Safety layer defaults (Patch P1/P2/P3): a plain, non-emergency
                // turn reports nothing red-flag-related, same as before this
                // field existed anywhere in Laravel.
                'emergency_detected' => false,
                'risk_level' => 'none',
                'red_flags' => [],
                'recommended_action' => null,
            ], $overrides)),
            // Hit whenever the interview reports finished=true (MedicalAssistantService
            // then calls the separate, stateless assessment endpoint). Faked so this
            // stays a Laravel-contract test, not a live call to the Python service.
            '*/api/assessment/run' => Http::response(array_merge([
                'features' => [],
                'predictions' => ['predictions' => [], 'predictor_version' => 'fake-v1'],
                'urgency' => ['level' => 'URGENT', 'score' => 0.75, 'explanation' => 'fake urgency'],
                'specialty' => ['specialty' => 'Neurology', 'confidence' => 0.7, 'explanation' => 'fake specialty'],
                'confidence' => ['overall_confidence' => 0.7, 'requires_human_review' => false, 'explanation' => 'fake confidence'],
                'explanation' => [
                    'summary' => 'Fake assessment summary',
                    'medical_reasoning' => 'Fake reasoning',
                    'recommendation' => 'Fake recommendation',
                    'disclaimer' => 'Fake disclaimer',
                ],
            ], $assessmentOverrides)),
        ]);
    }

    public function test_starting_a_conversation_returns_an_id_the_app_can_use(): void
    {
        $user = $this->patientUser();

        // Mirrors startNewConversation(): reads response.data.id
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/patient/conversations', [
            'title' => 'Symptom Check - 2026-07-23',
        ]);

        $response->assertStatus(201);
        $this->assertIsInt($response->json('data.id'));
    }

    public function test_text_message_response_exposes_the_flat_fields_the_chat_screen_reads(): void
    {
        $user = $this->patientUser();
        $this->fakeInterviewEngine();

        $conversationId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/patient/conversations', ['title' => 'Symptom Check'])
            ->json('data.id');

        // Mirrors handleSend(): reads response.question, .detected_symptoms, .finished
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/patient/conversations/{$conversationId}/messages", [
                'message' => 'I have a bad headache',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('question', 'How long have you had this headache?')
            ->assertJsonPath('detected_symptoms', ['headache'])
            ->assertJsonPath('finished', false);
    }

    public function test_finished_interview_reports_finished_true_and_returns_assessment_summary(): void
    {
        $user = $this->patientUser();
        $this->fakeInterviewEngine(['finished' => true, 'question' => null]);

        $conversationId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/patient/conversations', ['title' => 'Symptom Check'])
            ->json('data.id');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/patient/conversations/{$conversationId}/messages", [
                'message' => 'no other symptoms',
            ]);

        // "finished" no longer means an empty chat turn: MedicalAssistantService
        // now calls the assessment engine and returns its summary as "question"
        // (same field the mobile screen already reads), so the conversation
        // always ends with a visible result instead of silently going quiet.
        $response->assertStatus(201)
            ->assertJsonPath('finished', true)
            ->assertJsonPath('question', fn (?string $question) => $question !== null
                && str_contains($question, 'Fake assessment summary')
                && str_contains($question, 'Fake recommendation'));

        $this->assertDatabaseHas('assessments', [
            'conversation_id' => $conversationId,
            'triage' => 'High',
            'recommended_specialty' => 'Neurology',
        ]);
    }

    public function test_voice_message_response_shape_matches_the_text_message_shape(): void
    {
        $user = $this->patientUser();
        $this->fakeInterviewEngine();

        $conversationId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/patient/conversations', ['title' => 'Symptom Check'])
            ->json('data.id');

        // The transcription step itself is a separate AI call (Whisper); only
        // the interview-turn step is faked above, so fake speech-to-text too.
        Http::fake([
            '*/api/interview/turn' => Http::response([
                'session_id' => 'sess-123', 'finished' => false,
                'question' => 'How long have you had this headache?',
                'symptoms' => [['text' => 'headache', 'negated' => false]],
            ]),
            '*/api/speech-to-text' => Http::response([
                'success' => true, 'text' => 'I have a bad headache',
            ]),
        ]);

        $audio = UploadedFile::fake()->create('recording.mp3', 50, 'audio/mpeg');

        // Mirrors sendVoiceMessage(): multipart audio + conversation_id
        $response = $this->actingAs($user, 'sanctum')->post('/api/speech-to-text', [
            'conversation_id' => $conversationId,
            'audio' => $audio,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['text', 'question', 'detected_symptoms', 'finished']);
    }

    /**
     * Patch P1+P2+P3: when the interview turn reports an emergency, that
     * verdict must reach the /api/assessment/run request body as
     * interview_risk — not be silently dropped at InterviewService before
     * MedicalAssistantService/AssessmentService ever see it.
     */
    public function test_finished_interview_with_emergency_forwards_interview_risk_to_assessment(): void
    {
        $user = $this->patientUser();
        $this->fakeInterviewEngine([
            'finished' => true,
            'question' => null,
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
        ]);

        $conversationId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/patient/conversations', ['title' => 'Symptom Check'])
            ->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/patient/conversations/{$conversationId}/messages", [
                'message' => 'chest pain and shortness of breath',
            ])
            ->assertStatus(201);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return str_contains($request->url(), '/api/assessment/run')
                && $request['interview_risk'] === [
                    'emergency_detected' => true,
                    'risk_level' => 'immediate',
                ];
        });
    }

    /**
     * Backward compatibility: an interview/turn response shape without the
     * safety fields at all (simulating an older AI service build) must still
     * work end-to-end. InterviewService::sendMessage() defaults the missing
     * fields to false/'none' (same defensive-parsing style already used for
     * question/next_slot/symptoms in that method) rather than throwing, so
     * MedicalAssistantService always has a value to forward -- the assessment
     * request ends up with that safe default, not a crash and not an omitted
     * key. (AssessmentService::run()'s own "omit interview_risk when the
     * caller passes no 4th argument at all" path is covered directly at the
     * unit level by AssessmentServiceTest::test_interview_risk_is_omitted_entirely_when_not_provided.)
     */
    public function test_finished_interview_with_missing_safety_fields_sends_the_safe_default(): void
    {
        $user = $this->patientUser();
        Http::fake([
            '*/api/interview/turn' => Http::response([
                'session_id' => 'sess-legacy',
                'finished' => true,
                'question' => null,
                'symptoms' => [],
                // no emergency_detected/risk_level/red_flags/recommended_action at all
            ]),
            '*/api/assessment/run' => Http::response([
                'features' => [],
                'predictions' => ['predictions' => [], 'predictor_version' => 'fake-v1'],
                'urgency' => ['level' => 'NON_URGENT', 'score' => 0.25, 'explanation' => 'fake'],
                'specialty' => ['specialty' => 'General Medicine', 'confidence' => 0.5, 'explanation' => 'fake'],
                'confidence' => ['overall_confidence' => 0.5, 'requires_human_review' => false, 'explanation' => 'fake'],
                'explanation' => [
                    'summary' => 'fake', 'medical_reasoning' => 'fake',
                    'recommendation' => 'fake', 'disclaimer' => 'fake',
                ],
            ]),
        ]);

        $conversationId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/patient/conversations', ['title' => 'Symptom Check'])
            ->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/patient/conversations/{$conversationId}/messages", [
                'message' => 'no other symptoms',
            ])
            ->assertStatus(201);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return str_contains($request->url(), '/api/assessment/run')
                && $request['interview_risk'] === ['emergency_detected' => false, 'risk_level' => 'none'];
        });
    }

    public function test_a_patient_cannot_message_another_patients_conversation(): void
    {
        $owner = $this->patientUser();
        $intruder = $this->patientUser();

        $conversationId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/patient/conversations', ['title' => 'Private'])
            ->json('data.id');

        $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/patient/conversations/{$conversationId}/messages", ['message' => 'hi'])
            ->assertStatus(403);
    }
}
