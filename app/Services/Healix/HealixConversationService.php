<?php

namespace App\Services\Healix;

use App\Exceptions\AI\AIServiceException;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates ONE Healix AI triage turn: persist the patient's message,
 * call the real Python service, persist its reply, return both plus the
 * triage-specific fields (stage/specialty/reports) the frontend needs.
 *
 * A deliberately SEPARATE class from ConversationService/MedicalAssistantService,
 * not a branch inside either — checked both directly before deciding this.
 * ConversationController::storeMessage() -> ConversationService::sendMessage()
 * -> MedicalAssistantService::handleTextMessage() is hardwired end to end to
 * a DIFFERENT AI backend (the interview + assessment engine: Whisper +
 * MARBERT + a Python "finished"/turn-based interview flow,
 * config('services.medical_assistant')) — not Healix. That pipeline's
 * turn_number-per-question semantics, 'finished' flag, and MARBERT-derived
 * detected_symptoms don't apply here: Healix's own conversation memory
 * lives entirely in its LangGraph checkpointer, keyed by thread_id (see the
 * thread_id comment below) — Laravel doesn't need to reconstruct a
 * transcript from stored messages the way runAssessmentAndPersist() does
 * for the interview engine.
 *
 * What IS reused, deliberately: the Conversation/Message models and
 * MessageResource are generic conversation storage, not hardwired to
 * either AI backend's business logic — a Healix reply is just another
 * Message row (sender=assistant) on an existing Conversation, same
 * schema, same MessageResource shape the frontend already knows how to
 * render.
 *
 * Mirrors MedicalAssistantService::handleTextMessage()'s own structure
 * (DB::transaction wrapping the whole turn, turn_number via
 * max('turn_number')+1) for consistency, even though the transaction
 * here holds open across a potentially slow external HTTP call — same
 * tradeoff that method already accepts for its own AI call.
 */
class HealixConversationService
{
    public function __construct(
        protected HealixAiService $healix
    ) {}

    /**
     * @return array{
     *     patient_message: Message,
     *     assistant_message: Message,
     *     reply: string,
     *     stage: ?string,
     *     specialty: ?string,
     *     reports: ?array,
     *     available: bool
     * }
     */
    public function sendMessage(User $user, Conversation $conversation, string $text): array
    {
        return DB::transaction(function () use ($user, $conversation, $text) {
            $turn = $this->nextTurn($conversation);

            $patientMessage = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'sender' => Message::SENDER_PATIENT,
                'message_type' => Message::TYPE_TEXT,
                'message' => $text,
                'turn_number' => $turn,
            ]);

            // thread_id: the conversation's own primary key, NOT session_id.
            // session_id already belongs to the OTHER assistant's session
            // concept (MedicalAssistantService -> InterviewService /
            // AssessmentService — confirmed directly by reading that class
            // before reusing anything here, not assumed safe). Healix's
            // checkpointer only needs a stable, unique-per-conversation
            // string; the conversation's own id already guarantees both,
            // with nothing else to coordinate or collide with.
            $threadId = (string) $conversation->id;

            try {
                $result = $this->healix->sendMessage($user->patient, $threadId, $text);
            } catch (AIServiceException $e) {
                // Same "never let an AI-service outage roll back what's
                // already committed" posture as
                // MedicalAssistantService::runAssessmentAndPersist()'s own
                // try/catch: the patient's message is real and stays
                // saved; the reply just degrades to a plain notice instead
                // of the real triage turn.
                Log::warning('Healix AI turn failed, falling back to a plain unavailable notice', [
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage(),
                ]);

                $assistantMessage = Message::create([
                    'conversation_id' => $conversation->id,
                    'sender' => Message::SENDER_ASSISTANT,
                    'message_type' => Message::TYPE_TEXT,
                    'message' => __('ai.healix_unavailable_notice'),
                    'turn_number' => $turn,
                ]);

                return [
                    'patient_message' => $patientMessage,
                    'assistant_message' => $assistantMessage,
                    'reply' => $assistantMessage->message,
                    'stage' => null,
                    'specialty' => null,
                    'reports' => null,
                    'available' => false,
                ];
            }

            $replyText = (string) ($result['reply'] ?? '');

            $assistantMessage = Message::create([
                'conversation_id' => $conversation->id,
                'sender' => Message::SENDER_ASSISTANT,
                'message_type' => Message::TYPE_TEXT,
                'message' => $replyText,
                'turn_number' => $turn,
            ]);

            return [
                'patient_message' => $patientMessage,
                'assistant_message' => $assistantMessage,
                'reply' => $replyText,
                'stage' => $result['stage'] ?? null,
                'specialty' => $result['specialty'] ?? null,
                'reports' => $result['reports'] ?? null,
                'available' => true,
            ];
        });
    }

    protected function nextTurn(Conversation $conversation): int
    {
        return (int) ($conversation->messages()->max('turn_number') ?? 0) + 1;
    }
}
