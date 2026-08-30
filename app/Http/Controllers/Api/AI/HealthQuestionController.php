<?php

namespace App\Http\Controllers\Api\AI;

use App\Exceptions\AI\AIServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AskHealthQuestionRequest;
use App\Services\Healix\HealixAiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Relays Healix AI's separate Health Education Q&A route
 * (POST /health-questions — api/main.py on that side) to the frontend.
 *
 * A thin relay, same shape as HealixSpeechController: no Conversation/
 * Message persistence (this feature is stateless per question — see that
 * service's own docs/AHD_DATA_PROVENANCE.md), so there is nothing here
 * that an AI-service outage could leave half-committed. On failure this
 * returns a plain JSON error rather than HealixConversationService's
 * "available: false" degrade-and-persist pattern, since there is no
 * patient message being persisted here to protect from a rollback.
 *
 * Deliberately NOT wired through ConversationController /
 * HealixConversationService — this is a separate feature from triage
 * (docs/AHD_DATA_PROVENANCE.md's "Future frontend integration" section on
 * the Python side), not another kind of conversation turn.
 */
class HealthQuestionController extends Controller
{
    public function __construct(
        protected HealixAiClient $healix
    ) {}

    /**
     * POST /api/health-questions — mounted alongside chat/lab/ddi in
     * routes/api/ai.php, same auth:sanctum+verified gate as those
     * sibling routes (no role:patient restriction there either).
     *
     * thread_id sent to Python is the authenticated user's own id — an
     * audit-correlation tag only (see HealixAiClient::askHealthQuestion()'s
     * own doc comment for why reusing it across calls is safe here, unlike
     * the triage route's thread_id).
     */
    public function ask(AskHealthQuestionRequest $request): JsonResponse
    {
        set_time_limit(60);

        try {
            $result = $this->healix->askHealthQuestion(
                $request->validated('question'),
                (string) $request->user()->id,
                $request->validated('locale', 'ar'),
            );
        } catch (AIServiceException $e) {
            Log::error('Healix health-question request failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('ai.health_question_unavailable_notice'),
            ], $e->getCode());
        }

        return response()->json([
            'success' => true,
            'message' => __('ai.health_question_answered'),
            'answer' => $result['answer'] ?? '',
            'category' => $result['category'] ?? null,
            'sources' => $result['sources'] ?? [],
            'grounded' => (bool) ($result['grounded'] ?? false),
            'disclaimer' => $result['disclaimer'] ?? '',
            'retrieval_status' => $result['retrieval_status'] ?? null,
        ]);
    }
}
