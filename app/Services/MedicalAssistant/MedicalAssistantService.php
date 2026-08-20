<?php

namespace App\Services\MedicalAssistant;

use App\Exceptions\AI\AIServiceException;
use App\Models\Assessment;
use App\Models\Conversation;
use App\Models\ConversationSymptom;
use App\Models\DoctorSummary;
use App\Models\Message;
use App\Services\Interview\InterviewService;
use App\Services\SpecializationResolver;
use App\Support\UrgencyTriageMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * High-level orchestrator for the medical assistant conversation.
 *
 * Single entry point the conversation pipeline (text OR voice) calls. Drives
 * the history-taking interview engine, then, once it signals "finished",
 * hands the collected transcript + symptoms to the (separate, stateless)
 * assessment engine and turns its result into the final chat message:
 *
 *   Conversation -> MedicalAssistantService -> InterviewEngine -> LLM -> Assessment
 *
 * Laravel is the source of truth: this class owns persistence of the patient
 * message, the AI reply, extracted symptoms, the Python session id and status.
 * InterviewService/AssessmentService stay thin engine adapters (the Python
 * calls only).
 */
class MedicalAssistantService
{
    public function __construct(
        protected InterviewService $interview,
        protected AssessmentService $assessment,
        protected SpecializationResolver $specializationResolver
    ) {}

    /**
     * Handle a text message: persist it, then advance the interview.
     *
     * @return array{patient_message: Message, assistant_message: Message|null, result: array}
     */
    public function handleTextMessage(Conversation $conversation, string $text, ?int $senderId = null): array
    {
        return DB::transaction(function () use ($conversation, $text, $senderId) {
            $turn = $this->nextTurn($conversation);

            $patientMessage = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $senderId,
                'sender' => Message::SENDER_PATIENT,
                'message_type' => Message::TYPE_TEXT,
                'message' => $text,
                'turn_number' => $turn,
            ]);

            return $this->advanceInterview($conversation, $patientMessage, $text, $turn);
        });
    }

    /**
     * Handle an already-transcribed voice message: reuse the existing voice
     * Message as the patient turn, then advance the interview with the
     * transcribed text — exactly the same pipeline as text messages.
     *
     * @return array{patient_message: Message, assistant_message: Message|null, result: array}
     */
    public function handleVoiceMessage(
        Conversation $conversation,
        Message $voiceMessage,
        string $transcribedText,
        ?int $senderId = null
    ): array {
        return DB::transaction(function () use ($conversation, $voiceMessage, $transcribedText) {
            $turn = $this->nextTurn($conversation);
            $voiceMessage->forceFill(['turn_number' => $turn])->save();

            return $this->advanceInterview($conversation, $voiceMessage, $transcribedText, $turn);
        });
    }

    /**
     * Shared core: call the interview engine and persist its outcome.
     *
     * Future phases branch here (interview vs assessment) based on conversation
     * state — callers stay unchanged.
     */
    protected function advanceInterview(
        Conversation $conversation,
        Message $patientMessage,
        string $text,
        int $turn
    ): array {
        $result = $this->interview->sendMessage($text, $conversation->session_id);

        // Denormalized convenience: the symptoms MARBERT found on this turn.
        $patientMessage->forceFill([
            'detected_symptoms' => array_map(static fn ($s) => $s['text'], $result['symptoms']),
        ])->save();

        $this->persistSymptoms($conversation, $result['symptoms']);

        $assistantMessage = null;
        $assessmentData = null;
        if (! $result['finished'] && ! empty($result['question'])) {
            $assistantMessage = Message::create([
                'conversation_id' => $conversation->id,
                'sender' => Message::SENDER_ASSISTANT,
                'message_type' => Message::TYPE_TEXT,
                'message' => $result['question'],
                'turn_number' => $turn,
            ]);
        } elseif ($result['finished']) {
            ['message' => $assistantMessage, 'assessment' => $assessmentData] = $this->runAssessmentAndPersist(
                $conversation,
                $result['session_id'],
                $turn,
                $result
            );
        }

        $conversation->forceFill([
            'session_id' => $result['session_id'],
            'status' => $result['finished'] ? 'completed' : 'active',
            'ended_at' => $result['finished'] ? now() : $conversation->ended_at,
        ])->save();

        return [
            'patient_message' => $patientMessage,
            'assistant_message' => $assistantMessage,
            'result' => $result,
            'assessment' => $assessmentData,
        ];
    }

    protected function nextTurn(Conversation $conversation): int
    {
        return (int) ($conversation->messages()->max('turn_number') ?? 0) + 1;
    }

    /**
     * Run the (separate, stateless) assessment engine over the finished
     * interview's transcript + symptoms, and persist its result as the final
     * assistant message. The interview itself already succeeded by this
     * point, so an assessment failure must not roll back the transaction —
     * it degrades to a plain Arabic notice instead (same "never drop what's
     * already computed" spirit as the Python explainer this calls into).
     *
     * @param  array<string, mixed>  $interviewResult
     * @return array{message: Message, assessment: array<string, mixed>|null}
     */
    protected function runAssessmentAndPersist(
        Conversation $conversation,
        string $sessionId,
        int $turn,
        array $interviewResult = []
    ): array {
        $rawMessages = $conversation->messages()
            ->where('sender', Message::SENDER_PATIENT)
            ->orderBy('turn_number')
            ->get()
            ->map(fn (Message $m) => (string) ($m->message ?: $m->transcribed_text ?: ''))
            ->filter(fn (string $text) => $text !== '')
            ->values()
            ->all();

        $symptoms = $conversation->symptoms()->get()
            ->map(fn (ConversationSymptom $s) => [
                'text' => $s->symptom_text,
                'negated' => $s->negated,
                'confidence' => $s->confidence,
            ])->all();

        // Optional accelerant only (see AssessmentService::run()'s PHPDoc):
        // 'emergency_detected' being absent from $interviewResult (e.g. a
        // future caller that doesn't have it) keeps $interviewRisk null,
        // which keeps the request body exactly as it was before this change.
        $interviewRisk = array_key_exists('emergency_detected', $interviewResult)
            ? [
                'emergency_detected' => $interviewResult['emergency_detected'],
                'risk_level' => $interviewResult['risk_level'] ?? 'none',
            ]
            : null;

        $interviewRecord = isset($interviewResult['interview_record']) && is_array($interviewResult['interview_record'])
            ? $interviewResult['interview_record']
            : null;

        $assessmentData = null;

        try {
            $assessment = $this->assessment->run(
                $sessionId,
                $rawMessages,
                $symptoms,
                $interviewRisk,
                $interviewRecord
            );
            $this->persistAssessment($conversation, $assessment, $symptoms, $interviewResult);
            $assessmentData = $assessment;
            $text = $this->formatAssessmentSummary($assessment);
        } catch (AIServiceException $e) {
            Log::warning('Assessment run failed, falling back to a plain completion notice', [
                'conversation_id' => $conversation->id,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
            $text = __('ai.assessment_unavailable_notice');
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender' => Message::SENDER_ASSISTANT,
            'message_type' => Message::TYPE_TEXT,
            'message' => $text,
            'turn_number' => $turn,
        ]);

        return [
            'message' => $message,
            'assessment' => $assessmentData,
        ];
    }

    /**
     * @param  array<int, array{text: string, negated: bool, confidence: float|null}>  $symptoms
     * @param  array<string, mixed>  $interviewResult
     * @param  array{
     *     urgency: array{level: string, score: float, explanation: string},
     *     specialty: array{specialty: string, confidence: float, explanation: string},
     *     confidence: array{overall_confidence: float, requires_human_review: bool, explanation: string},
     *     explanation: array{summary: string, medical_reasoning: string, recommendation: string, disclaimer: string},
     *     predictions: array<int, array{disease: string, score: float, explanation: string}>
     * }  $assessment
     */
    protected function persistAssessment(
        Conversation $conversation,
        array $assessment,
        array $symptoms,
        array $interviewResult
    ): void {
        $redFlags = is_array($interviewResult['red_flags'] ?? null) ? $interviewResult['red_flags'] : [];
        $primaryRedFlag = $redFlags[0] ?? null;

        $recommendedSpecialty = (string) ($assessment['specialty']['specialty'] ?? 'General Medicine');

        // AI-service-provided code/name_ar (patient_summary.specialty) is the
        // preferred source -- it's the same resolution the Python side did
        // to build patient_summary. Falls back to a local name match when an
        // older AI service version doesn't send patient_summary yet.
        $specialtyLabel = $assessment['patient_summary']['specialty'] ?? null;
        // SpecializationResolver now matches name_ar only (Healix, the sole
        // AI backend going forward, sends an Arabic string) — prefer the
        // Arabic name_ar this AI service already provides in patient_summary
        // over the English $recommendedSpecialty, which can no longer
        // resolve. Same fallback-when-absent reasoning as $specialtyLabel's
        // own comment above: an older AI service response without
        // patient_summary falls back to the (now likely unresolvable)
        // English string rather than erroring.
        $specialization = $this->specializationResolver->resolve($specialtyLabel['name_ar'] ?? $recommendedSpecialty);

        $model = Assessment::updateOrCreate(
            ['conversation_id' => $conversation->id],
            [
                'status' => Assessment::STATUS_COMPLETED,
                'triage' => UrgencyTriageMapper::toLegacy((string) ($assessment['urgency']['level'] ?? 'NON_URGENT')),
                'recommended_specialty' => $recommendedSpecialty,
                'specialty_id' => $specialization?->id,
                'specialty_code' => $specialtyLabel['code'] ?? $specialization?->code,
                'specialty_name_ar' => $specialtyLabel['name_ar'] ?? $specialization?->name_ar,
                'possible_diseases' => array_map(static fn (array $prediction) => [
                    'disease' => $prediction['disease'] ?? '',
                    'score' => $prediction['score'] ?? 0.0,
                ], $assessment['predictions'] ?? []),
                'extracted_symptoms' => $symptoms,
                'emergency_detected' => (bool) ($interviewResult['emergency_detected'] ?? false),
                'emergency_type' => is_array($primaryRedFlag)
                    ? ($primaryRedFlag['rule_id'] ?? $primaryRedFlag['name_en'] ?? null)
                    : null,
                'risk_reason' => (string) ($assessment['urgency']['explanation'] ?? ''),
            ]
        );

        $this->persistDoctorSummary($conversation, $model, $assessment);
    }

    /**
     * Turns the (previously unused) doctor_summaries table into the actual
     * short medical report for the doctor -- filled from the AI service's
     * deterministic medical_report.content (no LLM invention on either side
     * of this call). doctor_id stays null until the patient books a
     * consultation (ConsultationService::bookConsultation() fills it in).
     */
    protected function persistDoctorSummary(Conversation $conversation, Assessment $assessment, array $result): void
    {
        $report = $result['medical_report'] ?? null;
        if (! is_array($report) || empty($report['content'])) {
            Log::warning('Assessment finished without a medical_report from the AI service', [
                'conversation_id' => $conversation->id,
                'assessment_id' => $assessment->id,
            ]);
            return;
        }

        DoctorSummary::updateOrCreate(
            ['conversation_id' => $conversation->id],
            [
                'assessment_id' => $assessment->id,
                'patient_id' => $conversation->patient_id,
                'summary' => $report['content'],
                'status' => DoctorSummary::STATUS_DRAFT,
            ]
        );
    }

    /**
     * @param  array{urgency: array{level: string, explanation: string}, specialty: array{specialty: string}, explanation: array{summary: string, recommendation: string, disclaimer: string}}  $assessment
     */
    protected function formatAssessmentSummary(array $assessment): string
    {
        $urgencyKey = 'ai.urgency_level_' . strtolower((string) $assessment['urgency']['level']);
        $urgencyLabel = __($urgencyKey);
        // Missing translation: __() returns the key itself -- fall back to the raw level instead.
        if ($urgencyLabel === $urgencyKey) {
            $urgencyLabel = $assessment['urgency']['level'];
        }

        $lines = [
            '📋 ' . __('ai.assessment_summary_title'),
            '',
            $assessment['explanation']['summary'],
            '',
            __('ai.assessment_urgency_line', ['level' => $urgencyLabel]),
            __('ai.assessment_specialty_line', ['specialty' => $assessment['specialty']['specialty']]),
            '',
            $assessment['explanation']['recommendation'],
            '',
            '⚠️ ' . $assessment['explanation']['disclaimer'],
        ];

        return implode("\n", $lines);
    }

    /**
     * Persist newly seen symptoms only (deduplicated by text + negation per
     * conversation) so the normalized table stays clean across turns.
     *
     * @param  array<int, array{text: string, negated: bool, confidence: float|null}>  $symptoms
     */
    protected function persistSymptoms(Conversation $conversation, array $symptoms): void
    {
        if (empty($symptoms)) {
            return;
        }

        $existing = [];
        foreach ($conversation->symptoms()->get(['symptom_text', 'negated']) as $row) {
            $existing[$row->symptom_text . '|' . (int) $row->negated] = true;
        }

        foreach ($symptoms as $symptom) {
            $text = $symptom['text'] ?? null;
            if (! is_string($text) || $text === '') {
                continue;
            }

            $key = $text . '|' . (int) ($symptom['negated'] ?? false);
            if (isset($existing[$key])) {
                continue;
            }

            ConversationSymptom::create([
                'conversation_id' => $conversation->id,
                'symptom_text' => $text,
                'negated' => (bool) ($symptom['negated'] ?? false),
                'confidence' => $symptom['confidence'] ?? null,
                'extracted_at' => now(),
            ]);

            $existing[$key] = true;
        }
    }
}
