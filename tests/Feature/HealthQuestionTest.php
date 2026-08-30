<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Locks in POST /api/health-questions (HealthQuestionController::ask) —
 * the Health Education Q&A relay to Healix's separate
 * POST /health-questions route. The Python service itself is faked at the
 * HTTP boundary (Http::fake), same approach HealixSpeechTest/
 * HealixConversationTest already use.
 */
class HealthQuestionTest extends TestCase
{
    use RefreshDatabase;

    private function patientUser(): User
    {
        $user = User::create([
            'full_name' => 'Health Question Patient',
            'email' => 'health-question-' . uniqid() . '@example.com',
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

    public function test_ask_relays_to_python_and_returns_the_answer_shape(): void
    {
        $user = $this->patientUser();

        Http::fake([
            '*/health-questions' => Http::response([
                'answer' => 'الربو مرض مزمن في الشعب الهوائية.',
                'category' => 'educational',
                'sources' => [
                    ['dataset' => 'AHD: Arabic Healthcare Dataset', 'category' => 'أمراض صدرية', 'license' => 'CC BY 4.0'],
                ],
                'grounded' => true,
                'disclaimer' => 'هاد المحتوى معلومات تثقيفية عامة فقط.',
                'retrieval_status' => 'sufficient',
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/health-questions', ['question' => 'ما هو الربو؟']);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('category', 'educational')
            ->assertJsonPath('grounded', true)
            ->assertJsonPath('retrieval_status', 'sufficient')
            ->assertJsonPath('sources.0.category', 'أمراض صدرية');

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), '/health-questions')
                && $request['question'] === 'ما هو الربو؟'
                && $request['locale'] === 'ar';
        });
    }

    public function test_ask_sends_the_authenticated_users_id_as_thread_id(): void
    {
        $user = $this->patientUser();

        Http::fake([
            '*/health-questions' => Http::response([
                'answer' => 'x', 'category' => 'educational', 'sources' => [],
                'grounded' => false, 'disclaimer' => 'x', 'retrieval_status' => 'insufficient',
            ], 200),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/health-questions', ['question' => 'سؤال عام']);

        Http::assertSent(function (Request $request) use ($user) {
            return str_contains($request->url(), '/health-questions')
                && $request['thread_id'] === (string) $user->id;
        });
    }

    public function test_ask_requires_authentication(): void
    {
        $this->postJson('/api/health-questions', ['question' => 'ما هو الربو؟'])
            ->assertStatus(401);
    }

    public function test_ask_requires_a_non_empty_question(): void
    {
        $user = $this->patientUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/health-questions', ['question' => ''])
            ->assertStatus(422);
    }

    public function test_ask_degrades_to_a_json_error_not_a_raw_exception_on_outage(): void
    {
        $user = $this->patientUser();

        Http::fake(['*/health-questions' => Http::response(['detail' => 'internal server error'], 500)]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/health-questions', ['question' => 'ما هو الربو؟']);

        // Same "500, not a raw exception" contract as HealixSpeechTest's
        // own outage tests — a fixed, translated notice, never Python's
        // raw detail text or a stack trace, since a health-question
        // outage has no prior established passthrough convention to
        // mirror the way the speech routes' *fixed* Python details do.
        $response->assertStatus(500)
            ->assertJsonPath('success', false);
        $this->assertStringNotContainsString('Exception', $response->getContent());
        $this->assertStringNotContainsString('.php', $response->getContent());
    }

    public function test_ask_does_not_create_any_conversation_or_message_row(): void
    {
        // This feature is stateless per question (docs/AHD_DATA_PROVENANCE.md
        // on the Python side) — deliberately not wired through
        // ConversationController/HealixConversationService.
        $user = $this->patientUser();

        Http::fake([
            '*/health-questions' => Http::response([
                'answer' => 'x', 'category' => 'educational', 'sources' => [],
                'grounded' => false, 'disclaimer' => 'x', 'retrieval_status' => 'insufficient',
            ], 200),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/health-questions', ['question' => 'ما هو الربو؟']);

        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
    }
}
