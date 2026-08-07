<?php

namespace App\Http\Controllers\Api\AI;

use App\Exceptions\AI\AIServiceException;
use App\Http\Controllers\Controller;
use App\Services\AI\ClinicalGuidanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Medical Knowledge & Clinical Guidance chat.
 *
 * Thin controller: authenticate (Sanctum) → validate → delegate to the
 * ClinicalGuidanceService, which builds the patient profile from MySQL and
 * calls the FastAPI service (Groq + WHO/CDC/NHS RAG + deterministic safety
 * floor). Arabic + English only.
 */
class ChatController extends Controller
{
    public function __construct(
        protected ClinicalGuidanceService $guidance
    ) {}

    /**
     * Optional session bootstrap for the chat screen. The service is
     * stateless per request (history is sent from the client), so this just
     * returns a friendly opener + the resolved locale.
     */
    public function startChat(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => __('ai.chat_response'),
            'data' => [
                'greeting' => __('ai.guidance_greeting'),
                'language' => app()->getLocale(),
            ],
        ]);
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'history' => ['sometimes', 'array', 'max:20'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string'],
            'language' => ['sometimes', 'string', 'max:5'],
            'country_code' => ['sometimes', 'string', 'size:2'],
        ]);

        try {
            $result = $this->guidance->ask(
                $request->user(),
                $validated['message'],
                $validated['history'] ?? [],
                $validated['language'] ?? null,
                $validated['country_code'] ?? null,
            );

            return response()->json([
                'success' => true,
                'message' => __('ai.message_sent'),
                'data' => [
                    'reply' => $result['reply'],
                    'risk_class' => $result['risk_class'],
                    'is_emergency' => $result['is_emergency'],
                    'provider' => $result['provider'],
                    'sources' => $result['sources'],
                    'diagnoses' => $result['diagnoses'],
                ],
            ]);
        } catch (AIServiceException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 502);
        }
    }
}
