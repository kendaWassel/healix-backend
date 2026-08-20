<?php

namespace App\Services\Healix;

use App\Exceptions\AI\AIServiceException;
use App\Models\Assessment;
use App\Models\Conversation;
use App\Models\DoctorSummary;
use App\Models\Message;
use App\Models\User;
use App\Services\SpecializationResolver;
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
        protected HealixAiService $healix,
        protected SpecializationResolver $specializationResolver
    ) {}

    /**
     * @return array{
     *     patient_message: Message,
     *     assistant_message: Message,
     *     reply: string,
     *     stage: ?string,
     *     is_crisis: bool,
     *     severity: ?string,
     *     red_flags: array,
     *     diagnosis: ?array,
     *     specialty: ?string,
     *     reports: ?array,
     *     available: bool,
     *     assessment_id: ?int
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
                    // Explicit, not just relying on the column defaults —
                    // this turn genuinely produced none of these (Python
                    // never ran), same "available: false means neutral
                    // defaults, not a real verdict" reasoning as below.
                    'is_crisis' => false,
                    'severity' => null,
                    'diagnosis' => null,
                    'specialty' => null,
                    'reports' => null,
                ]);

                return [
                    'patient_message' => $patientMessage,
                    'assistant_message' => $assistantMessage,
                    'reply' => $assistantMessage->message,
                    'stage' => null,
                    // Neutral, not-triggered defaults — same status as
                    // stage/specialty/reports above. Laravel has no basis
                    // to judge crisis/severity/red flags itself (that
                    // judgment lives entirely in the Python service's own
                    // rule-based + LLM layers); it did NOT run this turn,
                    // it did not come back "clear". `available: false`
                    // below is the field a caller must check first — never
                    // read is_crisis/severity/red_flags as a real safety
                    // verdict on a turn where available is false.
                    'is_crisis' => false,
                    'severity' => null,
                    'red_flags' => [],
                    'diagnosis' => null,
                    'specialty' => null,
                    'reports' => null,
                    'available' => false,
                    'assessment_id' => null,
                ];
            }

            $replyText = (string) ($result['reply'] ?? '');
            $stage = $result['stage'] ?? null;
            $isCrisis = (bool) ($result['is_crisis'] ?? false);

            $assistantMessage = Message::create([
                'conversation_id' => $conversation->id,
                'sender' => Message::SENDER_ASSISTANT,
                'message_type' => Message::TYPE_TEXT,
                'message' => $replyText,
                'turn_number' => $turn,
                // Persisted so GET .../messages (reopening this
                // conversation later) still has the diagnosis card,
                // specialty, and reports — previously only ever returned
                // in this one POST response, then lost (see
                // MessageResource's own doc comment).
                'is_crisis' => $isCrisis,
                'severity' => $result['severity'] ?? null,
                'diagnosis' => $result['diagnosis'] ?? null,
                'specialty' => $result['specialty'] ?? null,
                'reports' => $result['reports'] ?? null,
            ]);

            // Same "finished" condition the frontend already applies
            // client-side (stage === 'diagnosis' || isEmergency) — mirrored
            // here so ended_at (and therefore isFinished on a later
            // reopen) agrees with what the patient saw live, instead of
            // silently staying null forever for every Healix conversation
            // (MedicalAssistantService is the only thing that ever set
            // this column before).
            if (($stage === 'diagnosis' || $isCrisis) && $conversation->ended_at === null) {
                $conversation->forceFill(['ended_at' => now()])->save();
            }

            // The actual write that turns a finished differential into the
            // Assessment/DoctorSummary rows AssessmentController::bookingOptions()
            // and DoctorSummaryController::forPatient() read from — this
            // method already existed (persistTriageOutcome/persistDiagnosisOutcome,
            // both fully implemented) but was never called from here, so
            // booking/doctor-report never actually fired for a real Healix
            // turn despite the rest of that flow being built. $result is the
            // raw Python response (is_crisis/severity/red_flags/stage/
            // diagnosis/reports) — the same object read a few lines above,
            // not the method's own return array below.
            $assessment = $this->persistTriageOutcome($conversation, $result);

            return [
                'patient_message' => $patientMessage,
                'assistant_message' => $assistantMessage,
                'reply' => $replyText,
                'stage' => $result['stage'] ?? null,
                // Null on every non-diagnosis turn (nothing to book yet).
                // The frontend's booking screen needs this id to call
                // GET /patient/assessments/{id}/booking — there was
                // previously no way for a client to learn it at all.
                'assessment_id' => $assessment?->id,
                // Safety-critical fields (CLAUDE.md's own non-negotiable
                // safety rules on the Python side) — previously received
                // from $result but silently dropped here, never reaching
                // the frontend at all. is_crisis in particular duplicates
                // stage === "crisis" by the Python contract's own design
                // (api.contracts.ChatResponse's own docstring), kept as
                // its own field for the same reason that contract keeps
                // it: a boolean a caller can check directly without
                // parsing/comparing a string.
                'is_crisis' => (bool) ($result['is_crisis'] ?? false),
                'severity' => $result['severity'] ?? null,
                'red_flags' => $result['red_flags'] ?? [],
                'diagnosis' => $result['diagnosis'] ?? null,
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

    /**
     * Persists this turn's safety snapshot onto the conversation
     * (is_crisis/severity/red_flags — migration
     * 2026_08_20_114916_add_healix_safety_fields_to_conversations_table),
     * and, only when this turn actually reached a real differential
     * (stage=="diagnosis"), the Assessment/DoctorSummary rows the booking
     * and doctor-facing flows read from.
     *
     * Only ever called from sendMessage()'s SUCCESS branch — never from the
     * AIServiceException catch above, whose neutral defaults
     * (is_crisis=false, severity=null, red_flags=[]) are explicitly NOT a
     * real safety verdict (see that branch's own comment: "it did NOT run
     * this turn, it did not come back 'clear'"). Overwriting a
     * conversation's last real snapshot with those defaults just because
     * this turn's call failed would silently erase a genuine prior
     * crisis/emergency state.
     *
     * Returns the Assessment this turn produced (or updated), or null on
     * any turn that didn't reach a real differential — the frontend's
     * booking screen needs that id, see sendMessage()'s own return array.
     */
    protected function persistTriageOutcome(Conversation $conversation, array $result): ?Assessment
    {
        $conversation->forceFill([
            'is_crisis' => (bool) ($result['is_crisis'] ?? false),
            'severity' => $result['severity'] ?? null,
            'red_flags' => $result['red_flags'] ?? [],
        ])->save();

        if (($result['stage'] ?? null) === 'diagnosis' && !empty($result['diagnosis'])) {
            return $this->persistDiagnosisOutcome($conversation, $result);
        }

        return null;
    }

    /**
     * Turns a finished Healix differential into the same Assessment +
     * DoctorSummary rows the (retired) legacy MedicalAssistantService
     * pipeline used to produce — MedicalAssistantService::persistAssessment/
     * persistDoctorSummary is this method's own direct model, field for
     * field, so booking (AssessmentBookingService) and the doctor-facing
     * report flow work identically regardless of which pipeline produced
     * a given Assessment row.
     *
     * Only ever called when $result['stage'] === 'diagnosis' (see
     * persistTriageOutcome above). CLAUDE.md (Python side)'s own graph flow
     * guarantees check_red_flags runs BEFORE diagnose and routes straight
     * to emergency_node on any match, so a turn that reaches this point
     * provably had no red flags this turn — emergency_detected/
     * emergency_type/risk_reason are set to false/null/null
     * unconditionally below, not defensively guessed.
     */
    protected function persistDiagnosisOutcome(Conversation $conversation, array $result): Assessment
    {
        $diagnosis = $result['diagnosis'];
        $differential = is_array($diagnosis['differential'] ?? null) ? $diagnosis['differential'] : [];

        // Healix's own $result['specialty'] IS specialty_laravel (see
        // api/main.py on the Python side: ChatResponse.specialty is built
        // from state["specialty_laravel"], never the raw KB state["specialty"]
        // string) — already translated into one of Laravel's real
        // specializations.name_ar values, or the free-text referral phrase
        // when nothing maps. SpecializationResolver matches name_ar
        // directly for exactly this reason.
        $specialtyLaravel = $result['specialty'] ?? null;
        $specialization = $this->specializationResolver->resolve($specialtyLaravel);

        // match_score is only meaningful WITHIN one disease's own match,
        // never comparable ACROSS different diseases in this differential —
        // the knowledge base is still too small/uneven for that comparison
        // to be valid (CLAUDE.md > Known limitations, Python side). If this
        // list is ever rendered to a doctor as a ranked/compared set rather
        // than shown per-entry on its own, that comparison is not
        // established yet.
        $possibleDiseases = array_map(static fn (array $entry) => [
            'disease' => $entry['name_ar'] ?? $entry['name'] ?? '',
            'score' => $entry['match_score'] ?? 0.0,
        ], $differential);

        $assessment = Assessment::updateOrCreate(
            ['conversation_id' => $conversation->id],
            [
                'status' => Assessment::STATUS_COMPLETED,
                // Severity grading for the normal, non-emergency diagnostic
                // path is unimplemented on the Python side (CLAUDE.md > Known
                // limitations) — there is no source for this field from
                // Healix today. Left null rather than guessed.
                'triage' => null,
                'recommended_specialty' => $specialtyLaravel,
                'specialty_id' => $specialization?->id,
                'specialty_code' => $specialization?->code,
                'specialty_name_ar' => $specialization?->name_ar,
                'possible_diseases' => $possibleDiseases,
                // Not part of api/contracts.py's ChatResponse today — left
                // empty rather than adding a cross-repo contract change
                // this close to submission.
                'extracted_symptoms' => [],
                'emergency_detected' => false,
                'emergency_type' => null,
                'risk_reason' => null,
            ]
        );

        $doctorReport = $result['reports']['doctor'] ?? null;
        if (!is_string($doctorReport) || $doctorReport === '') {
            Log::warning('Healix diagnosis turn finished without a doctor report', [
                'conversation_id' => $conversation->id,
                'assessment_id' => $assessment->id,
            ]);
            return $assessment;
        }

        DoctorSummary::updateOrCreate(
            ['conversation_id' => $conversation->id],
            [
                'assessment_id' => $assessment->id,
                'patient_id' => $conversation->patient_id,
                // reports.patient stays in the conversation's message
                // history only (it's already this turn's assistant Message
                // text) — never copied here. reports.doctor is the
                // doctor-facing register Healix's own design produces
                // specifically for this table (CLAUDE.md > Graph flow,
                // nodes/generate_reports.py).
                'summary' => $doctorReport,
                'status' => DoctorSummary::STATUS_DRAFT,
            ]
        );

        return $assessment;
    }
}
