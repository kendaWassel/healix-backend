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
