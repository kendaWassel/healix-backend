<?php

namespace App\Services\MedicalAssistant;

use App\Models\Conversation;
use App\Models\ConversationSymptom;
use App\Models\Message;
use App\Services\Interview\InterviewService;
use Illuminate\Support\Facades\DB;

/**
 * High-level orchestrator for the medical assistant conversation.
 *
 * Single entry point the conversation pipeline (text OR voice) calls. Today it
 * drives the history-taking interview engine; later it will also route to
 * assessment/diagnosis without changing its callers:
 *
 *   Conversation -> MedicalAssistantService -> InterviewEngine -> LLM (later) -> Assessment (later)
 *
 * Laravel is the source of truth: this class owns persistence of the patient
 * message, the AI reply, extracted symptoms, the Python session id and status.
 * InterviewService stays a thin engine adapter (the Python call only).
 */
class MedicalAssistantService
{
    public function __construct(
        protected InterviewService $interview
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

        $assistantMessage = null;
        if (! $result['finished'] && ! empty($result['question'])) {
            $assistantMessage = Message::create([
                'conversation_id' => $conversation->id,
                'sender' => Message::SENDER_ASSISTANT,
                'message_type' => Message::TYPE_TEXT,
                'message' => $result['question'],
                'turn_number' => $turn,
            ]);
        }

        $this->persistSymptoms($conversation, $result['symptoms']);

        $conversation->forceFill([
            'session_id' => $result['session_id'],
            'status' => $result['finished'] ? 'completed' : 'active',
            'ended_at' => $result['finished'] ? now() : $conversation->ended_at,
        ])->save();

        return [
            'patient_message' => $patientMessage,
            'assistant_message' => $assistantMessage,
            'result' => $result,
        ];
    }

    protected function nextTurn(Conversation $conversation): int
    {
        return (int) ($conversation->messages()->max('turn_number') ?? 0) + 1;
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
