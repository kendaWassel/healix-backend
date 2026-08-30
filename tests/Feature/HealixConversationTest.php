<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Conversation;
use App\Models\DoctorSummary;
use App\Models\Patient;
use App\Models\Specialization;
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

    /**
     * Every real HTTP request Http::fake() doesn't explicitly match still
     * goes out over the real network (Laravel does NOT stub unmatched
     * URLs by default) — confirmed directly: without this, the new
     * classification call HealixConversationService::sendMessage() now
     * makes to /health-questions (merged health-education Q&A) tried a
     * real connection to the configured (unrunning) local Healix service,
     * slow and flaky. Faked here with a category that is neither
     * 'educational' nor 'out_of_scope', so every existing test's
     * assumption that the turn falls through to the real /chat call below
     * keeps holding exactly as before.
     */
    private function fakeHealthQuestionsClassifyOnly(): array
    {
        return [
            '*/health-questions' => Http::response([
                'answer' => 'redirect message',
                'category' => 'triage_redirect',
                'sources' => [],
                'grounded' => false,
                'disclaimer' => '',
                'retrieval_status' => 'insufficient',
            ]),
        ];
    }

    private function fakeHealixChat(array $overrides = []): void
    {
        Http::fake(array_merge($this->fakeHealthQuestionsClassifyOnly(), [
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
        ]));
    }

    /**
     * Merged health-education Q&A (HealixConversationService::sendMessage()'s
     * new classify-first step): an 'educational' classification is answered
     * directly, through the SAME /healix-messages endpoint the frontend
     * already calls — no separate route, no frontend change. Confirms the
     * real /chat triage call is never made for this turn.
     */
    public function test_an_educational_question_is_answered_directly_without_touching_triage(): void
    {
        $user = $this->patientUser();

        Http::fake([
            '*/health-questions' => Http::response([
                'answer' => 'الفيتامين د بيلعب دور بصحة العظام والمناعة.',
                'category' => 'educational',
                'sources' => [['dataset' => 'AHD: Arabic Healthcare Dataset', 'category' => 'Nutrition', 'license' => 'CC BY 4.0']],
                'grounded' => true,
                'disclaimer' => 'هاد معلومات تثقيفية عامة فقط.',
                'retrieval_status' => 'sufficient',
            ]),
            // Deliberately NOT faking /chat — if the code wrongly fell
            // through to it, this would attempt a real network call and
            // the test would fail/hang, proving the short-circuit works.
        ]);

        $conversationId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/patient/conversations', ['title' => 'Symptom Check'])
            ->json('data.id');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/patient/conversations/{$conversationId}/healix-messages", [
                'message' => 'شو فوائد فيتامين د؟',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('available', true)
            ->assertJsonPath('stage', null)
            ->assertJsonPath('reply', 'الفيتامين د بيلعب دور بصحة العظام والمناعة.');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/chat'));

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversationId,
            'sender' => 'assistant',
            'message' => 'الفيتامين د بيلعب دور بصحة العظام والمناعة.',
        ]);
    }

    /**
     * The mirror case: a 'triage_redirect' classification (the message
     * looks like a personal symptom, not a general question) falls
     * through to the real, unmodified triage call exactly as before the
     * merge — proving the new classify step never blocks a real triage
     * turn, it only intercepts genuine general-knowledge questions.
     */
    public function test_a_personal_symptom_classification_still_reaches_real_triage(): void
    {
        $user = $this->patientUser();
        $this->fakeHealixChat(); // classifies as 'triage_redirect' by default

        $conversationId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/patient/conversations', ['title' => 'Symptom Check'])
            ->json('data.id');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/patient/conversations/{$conversationId}/healix-messages", [
                'message' => 'عندي صداع شديد من يومين',
            ]);

        $response->assertStatus(201)->assertJsonPath('stage', 'diagnosis');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/chat'));
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

        // thread_id sent to Python is the conversation's own healix_thread_id
        // (a UUID, assigned once at creation) — never the raw auto-increment
        // id, which is resettable and was the root cause of a real,
        // reproduced cross-conversation state-leak bug (see
        // HealixConversationService::sendMessage()'s own comment).
        $expectedThreadId = Conversation::find($conversationId)->healix_thread_id;
        Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($expectedThreadId) {
            return str_contains($request->url(), '/chat')
                && $request['thread_id'] === $expectedThreadId;
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
        $threadId = Conversation::find($conversationId)->healix_thread_id;

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/patient/conversations/{$conversationId}/healix-messages", [
                'message' => 'عندي صداع نابض من جهة وحدة مع غثيان',
            ]);

        // The turn now also makes a first, separate call to /health-questions
        // to classify the message (merged health-education Q&A — see
        // HealixConversationService::sendMessage()'s own comment) before the
        // real /chat call below — both log identical generic message text
        // via the shared FastApiClient::send(), so these closures check the
        // URL too, to isolate the assertion to the real triage call this
        // test is actually about.
        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context) use ($threadId) {
                return $message === 'Healix AI triage service request'
                    && str_contains($context['url'], '/chat')
                    && $context['payload'] === ['thread_id' => $threadId]
                    && ! str_contains(json_encode($context), 'صداع');
            })
            ->once();

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context) {
                return $message === 'Healix AI triage service response'
                    && str_contains($context['url'], '/chat')
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
        Http::fake(array_merge($this->fakeHealthQuestionsClassifyOnly(), [
            '*/chat' => Http::response(['detail' => 'internal server error'], 500),
        ]));

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

    /**
     * stage=="diagnosis" is the one outcome that must persist an Assessment
     * + DoctorSummary — HealixConversationService::persistDiagnosisOutcome,
     * the direct replacement for the retired legacy pipeline's own
     * MedicalAssistantService::persistAssessment/persistDoctorSummary.
     */
    public function test_a_diagnosis_stage_turn_persists_assessment_and_doctor_summary(): void
    {
        $user = $this->patientUser();
        Specialization::create(['name' => 'Neurology', 'name_ar' => 'الأمراض العصبية', 'code' => 'neurology']);

        $this->fakeHealixChat([
            'specialty' => 'الأمراض العصبية',
            'diagnosis' => [
                'status' => 'differential',
                'differential' => [
                    [
                        'name' => 'Migraine',
                        'name_ar' => 'الشقيقة',
                        'match_score' => 1.0,
                        'certainty' => 'high',
                        'specialties' => ['عصبية'],
                    ],
                ],
                'reasoning' => 'تطابق تام',
            ],
            'reports' => ['patient' => 'fake patient report', 'doctor' => 'ملخص طبي للدكتور'],
        ]);

        $conversationId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/patient/conversations', ['title' => 'Symptom Check'])
            ->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/patient/conversations/{$conversationId}/healix-messages", [
                'message' => 'عندي صداع نابض من جهة وحدة مع غثيان',
            ])
            ->assertStatus(201);

        $assessment = Assessment::where('conversation_id', $conversationId)->first();
        $this->assertNotNull($assessment);
        $this->assertSame(Assessment::STATUS_COMPLETED, $assessment->status);
        // Severity grading for the normal, non-emergency path is
        // unimplemented on the Python side — deliberately null, not guessed.
        $this->assertNull($assessment->triage);
        $this->assertSame('الأمراض العصبية', $assessment->recommended_specialty);
        $this->assertNotNull($assessment->specialty_id);
        $this->assertSame('neurology', $assessment->specialty_code);
        $this->assertSame('الأمراض العصبية', $assessment->specialty_name_ar);
        // assertEquals, not assertSame: match_score round-trips through
        // JSON (HTTP fake -> Assessment's array cast -> MySQL JSON column
        // -> read back), and a whole-number float loses its float-ness
        // through that path (1.0 decodes back as int 1) -- semantically
        // identical, not a real int/float distinction worth failing on.
        $this->assertEquals([['disease' => 'الشقيقة', 'score' => 1.0]], $assessment->possible_diseases);
        // Not part of api/contracts.py's ChatResponse today — left empty.
        $this->assertSame([], $assessment->extracted_symptoms);
        // Structurally guaranteed: check_red_flags runs before diagnose and
        // routes straight to emergency_node on any match, so a
        // stage=="diagnosis" turn provably had no red flags this turn.
        $this->assertFalse($assessment->emergency_detected);
        $this->assertNull($assessment->emergency_type);
        $this->assertNull($assessment->risk_reason);

        $this->assertDatabaseHas('doctor_summaries', [
            'conversation_id' => $conversationId,
            'assessment_id' => $assessment->id,
            'summary' => 'ملخص طبي للدكتور',
            'status' => DoctorSummary::STATUS_DRAFT,
            'doctor_id' => null,
        ]);

        $conversation = Conversation::find($conversationId);
        $this->assertFalse($conversation->is_crisis);
        $this->assertNull($conversation->severity);
        $this->assertSame([], $conversation->red_flags);
    }

    /**
     * A crisis turn updates the new conversation safety columns, but must
     * NOT create a booking-eligible Assessment — the terminal-outcome
     * gating in persistTriageOutcome (stage=="diagnosis" only).
     */
    public function test_a_crisis_stage_turn_updates_conversation_safety_fields_without_creating_an_assessment(): void
    {
        $user = $this->patientUser();

        $this->fakeHealixChat([
            'stage' => 'crisis',
            'is_crisis' => true,
            'severity' => null,
            'red_flags' => [],
            'diagnosis' => null,
            'specialty' => null,
            'reports' => null,
            'reply' => 'ردة فعل الأزمة',
        ]);

        $conversationId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/patient/conversations', ['title' => 'Symptom Check'])
            ->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/patient/conversations/{$conversationId}/healix-messages", [
                'message' => 'بدي موت',
            ])
            ->assertStatus(201);

        $this->assertTrue(Conversation::find($conversationId)->is_crisis);

        $this->assertDatabaseMissing('assessments', ['conversation_id' => $conversationId]);
        $this->assertDatabaseMissing('doctor_summaries', ['conversation_id' => $conversationId]);
    }

    /**
     * Same terminal-outcome gating, exercised for the emergency/red-flags
     * path specifically (a real, non-empty red_flags payload — not just
     * is_crisis) — and confirms it's actually written to the new json
     * column correctly, not just left at its boolean/null defaults.
     */
    public function test_an_emergency_stage_turn_persists_red_flags_and_severity_without_creating_an_assessment(): void
    {
        $user = $this->patientUser();

        $this->fakeHealixChat([
            'stage' => 'emergency',
            'is_crisis' => false,
            'severity' => 'emergency',
            'red_flags' => [['id' => 'acs_chest_pain', 'reason' => 'ألم صدر مترافق مع ضيق تنفس']],
            'diagnosis' => null,
            'specialty' => null,
            'reports' => null,
        ]);

        $conversationId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/patient/conversations', ['title' => 'Symptom Check'])
            ->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/patient/conversations/{$conversationId}/healix-messages", [
                'message' => 'عندي الم صدر شديد وضيق تنفس',
            ])
            ->assertStatus(201);

        $conversation = Conversation::find($conversationId);
        $this->assertFalse($conversation->is_crisis);
        $this->assertSame('emergency', $conversation->severity);
        $this->assertSame(
            [['id' => 'acs_chest_pain', 'reason' => 'ألم صدر مترافق مع ضيق تنفس']],
            $conversation->red_flags
        );

        $this->assertDatabaseMissing('assessments', ['conversation_id' => $conversationId]);
    }

    /**
     * The AIServiceException fallback branch's neutral defaults
     * (is_crisis=false, severity=null, red_flags=[]) are NOT a real safety
     * verdict — persistTriageOutcome must never run for that branch, or an
     * outage turn would silently erase a genuine prior crisis/emergency
     * snapshot.
     */
    public function test_an_unavailable_healix_turn_does_not_overwrite_a_previous_crisis_flag(): void
    {
        $user = $this->patientUser();

        // A single Http::fake() with a two-step sequence — a second,
        // separate Http::fake() call partway through the test does NOT
        // reliably override the first call's rule for this Laravel
        // version's resolution order (verified directly: it did not take
        // effect when tried), so this is the correct tool, not a style
        // preference.
        Http::fake(array_merge($this->fakeHealthQuestionsClassifyOnly(), [
            '*/chat' => Http::sequence()
                ->push([
                    'thread_id' => '1',
                    'reply' => 'ردة فعل الأزمة',
                    'stage' => 'crisis',
                    'is_crisis' => true,
                    'severity' => null,
                    'red_flags' => [],
                    'diagnosis' => null,
                    'specialty' => null,
                    'reports' => null,
                ])
                // Not a second ->push(): FastApiClient retries on 5xx, so
                // the failing turn consumes more than one sequence item —
                // whenEmpty() keeps returning 500 for every retry attempt,
                // not just the first.
                ->whenEmpty(Http::response(['detail' => 'internal server error'], 500)),
        ]));

        $conversationId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/patient/conversations', ['title' => 'Symptom Check'])
            ->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/patient/conversations/{$conversationId}/healix-messages", ['message' => 'بدي موت'])
            ->assertStatus(201);

        $this->assertTrue(Conversation::find($conversationId)->is_crisis);

        // The AI service is now down for this second turn on the same
        // thread (the sequence's second, 500-status push).
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/patient/conversations/{$conversationId}/healix-messages", ['message' => 'مرحبا'])
            ->assertStatus(201)
            ->assertJsonPath('available', false);

        // available=false is not a real "all clear" -- the last REAL
        // snapshot must survive an outage turn untouched.
        $this->assertTrue(Conversation::find($conversationId)->is_crisis);
    }
}
