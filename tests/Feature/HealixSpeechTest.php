<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Locks in the two Healix speech relay routes
 * (HealixSpeechController::transcribe/synthesize) — the Python service
 * itself is faked at the HTTP boundary (Http::fake), same approach
 * HealixConversationTest already uses for POST /chat.
 */
class HealixSpeechTest extends TestCase
{
    use RefreshDatabase;

    private function patientUser(): User
    {
        $user = User::create([
            'full_name' => 'Healix Speech Patient',
            'email' => 'healix-speech-' . uniqid() . '@example.com',
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

    private function createConversation(User $user): int
    {
        return $this->actingAs($user, 'sanctum')
            ->postJson('/api/patient/conversations', ['title' => 'Voice Check'])
            ->json('data.id');
    }

    // --- transcribe --------------------------------------------------------

    public function test_transcribe_relays_to_python_and_returns_text(): void
    {
        $user = $this->patientUser();
        $conversationId = $this->createConversation($user);

        Http::fake([
            '*/speech/transcribe' => Http::response(['text' => 'عندي صداع'], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->post("/api/patient/conversations/{$conversationId}/healix-speech/transcribe", [
                'audio' => UploadedFile::fake()->createWithContent('rec.mp3', 'fake-mp3-bytes'),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('text', 'عندي صداع');

        // Multipart field sent to Python is 'file', not Laravel's own
        // 'audio' — HealixAiClient::transcribe()'s own renaming.
        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return str_contains($request->url(), '/speech/transcribe')
                && $request->hasFile('file');
        });
    }

    public function test_transcribe_does_not_create_a_message_row(): void
    {
        // Deliberate design: this is a pure relay, the real patient turn
        // is created later by POST .../healix-messages with the returned
        // text (HealixSpeechController's own docstring) — never here.
        $user = $this->patientUser();
        $conversationId = $this->createConversation($user);

        Http::fake(['*/speech/transcribe' => Http::response(['text' => 'عندي صداع'], 200)]);

        $this->actingAs($user, 'sanctum')
            ->post("/api/patient/conversations/{$conversationId}/healix-speech/transcribe", [
                'audio' => UploadedFile::fake()->createWithContent('rec.mp3', 'fake-mp3-bytes'),
            ]);

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_transcribe_requires_authentication(): void
    {
        $response = $this->post('/api/patient/conversations/1/healix-speech/transcribe', [
            'audio' => UploadedFile::fake()->createWithContent('rec.mp3', 'fake-mp3-bytes'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(401);
    }

    public function test_a_patient_cannot_transcribe_into_another_patients_conversation(): void
    {
        $owner = $this->patientUser();
        $intruder = $this->patientUser();
        $conversationId = $this->createConversation($owner);

        Http::fake(['*/speech/transcribe' => Http::response(['text' => 'x'], 200)]);

        $this->actingAs($intruder, 'sanctum')
            ->post("/api/patient/conversations/{$conversationId}/healix-speech/transcribe", [
                'audio' => UploadedFile::fake()->createWithContent('rec.mp3', 'fake-mp3-bytes'),
            ])
            ->assertStatus(403);

        Http::assertNotSent(function (\Illuminate\Http\Client\Request $request) {
            return str_contains($request->url(), '/speech/transcribe');
        });
    }

    public function test_transcribe_degrades_to_a_json_error_not_a_raw_exception_on_outage(): void
    {
        $user = $this->patientUser();
        $conversationId = $this->createConversation($user);

        Http::fake(['*/speech/transcribe' => Http::response(['detail' => 'internal server error'], 500)]);

        $response = $this->actingAs($user, 'sanctum')
            ->post("/api/patient/conversations/{$conversationId}/healix-speech/transcribe", [
                'audio' => UploadedFile::fake()->createWithContent('rec.mp3', 'fake-mp3-bytes'),
            ]);

        // 500, not 503: FastApiClient::send()/postBinary() only throws the
        // 503 AIServiceUnavailableException for a genuine CONNECTION
        // failure exhausting retries — a server that responds every time,
        // even with a persistent 500, surfaces as AIServiceException
        // carrying that same status (500 here), a distinct, intentional
        // difference from "truly unreachable".
        //
        // The message DOES legitimately equal Python's own 'detail' text
        // here — same passthrough HealixSpeechController::transcribe()
        // deliberately mirrors from the existing SpeechController's own
        // AIServiceException catch block (this project's established
        // pattern, not something introduced here). Safe specifically
        // because api/main.py's own 'detail' strings are always fixed,
        // generic constants (_UPSTREAM_ERROR_DETAIL/_INTERNAL_ERROR_DETAIL)
        // that never carry patient-derived content — this is not a raw
        // exception/stack-trace leak, just a controlled, safe string.
        $response->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'internal server error');
        $this->assertStringNotContainsString('Exception', $response->getContent());
        $this->assertStringNotContainsString('.php', $response->getContent());
    }

    public function test_transcribe_rejects_a_non_audio_file(): void
    {
        $user = $this->patientUser();
        $conversationId = $this->createConversation($user);

        $response = $this->actingAs($user, 'sanctum')
            ->post("/api/patient/conversations/{$conversationId}/healix-speech/transcribe", [
                'audio' => UploadedFile::fake()->createWithContent('doc.pdf', '%PDF-1.4 fake pdf content'),
            ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
    }

    // --- synthesize ----------------------------------------------------------

    public function test_synthesize_relays_to_python_and_returns_mp3_bytes(): void
    {
        $user = $this->patientUser();

        Http::fake([
            '*/speech/synthesize' => Http::response("\xff\xfb\x90\x00", 200, ['Content-Type' => 'audio/mpeg']),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/patient/healix-speech/synthesize', ['text' => 'مرحبا']);

        $response->assertStatus(200);
        $this->assertSame('audio/mpeg', $response->headers->get('Content-Type'));
        $this->assertSame("\xff\xfb\x90\x00", $response->getContent());

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return str_contains($request->url(), '/speech/synthesize')
                && $request['text'] === 'مرحبا';
        });
    }

    public function test_synthesize_requires_authentication(): void
    {
        $this->postJson('/api/patient/healix-speech/synthesize', ['text' => 'مرحبا'])
            ->assertStatus(401);
    }

    public function test_synthesize_requires_non_empty_text(): void
    {
        $user = $this->patientUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/patient/healix-speech/synthesize', ['text' => ''])
            ->assertStatus(422);
    }

    public function test_synthesize_degrades_to_a_json_error_not_a_raw_exception_on_outage(): void
    {
        $user = $this->patientUser();

        Http::fake(['*/speech/synthesize' => Http::response(['detail' => 'internal server error'], 500)]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/patient/healix-speech/synthesize', ['text' => 'مرحبا']);

        // Same reasoning as the transcribe outage test above: a persistent
        // 500 (not a connection failure) surfaces as 500, and Python's own
        // fixed, generic 'detail' string legitimately passes through —
        // see that test's comment for why this is safe, not a leak.
        $response->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'internal server error');
        $this->assertStringNotContainsString('Exception', $response->getContent());
    }

    public function test_synthesize_is_not_scoped_to_a_conversation(): void
    {
        // Any authenticated patient can synthesize any text — no
        // conversation ownership check applies (HealixSpeechController's
        // own docstring: synthesize() doesn't read/write conversation data).
        $user = $this->patientUser();

        Http::fake(['*/speech/synthesize' => Http::response('audio-bytes', 200, ['Content-Type' => 'audio/mpeg'])]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/patient/healix-speech/synthesize', ['text' => 'نص عشوائي'])
            ->assertStatus(200);
    }
}
