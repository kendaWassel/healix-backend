<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConversationRequest;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Services\ConversationService;
use App\Services\Healix\HealixConversationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ConversationService $conversationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Conversation::class);

        $conversations = $this->conversationService->listForPatient(
            $request->user(),
            (int) $request->integer('per_page', 15)
        );

        return response()->json([
            'success' => true,
            'message' => __('ai.conversations_retrieved'),
            'data' => ConversationResource::collection($conversations)->response()->getData(true),
        ]);
    }

    public function store(StoreConversationRequest $request): JsonResponse
    {
        $this->authorize('create', Conversation::class);

        try {
            $conversation = $this->conversationService->create(
                $request->user(),
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => __('ai.conversation_created'),
                'data' => new ConversationResource($conversation),
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500);
        }
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $conversation->load('latestMessage')->loadCount('messages');

        return response()->json([
            'success' => true,
            'message' => __('ai.conversation_retrieved'),
            'data' => new ConversationResource($conversation),
        ]);
    }

    public function destroy(Conversation $conversation): JsonResponse
    {
        $this->authorize('delete', $conversation);

        $this->conversationService->delete($conversation);

        return response()->json([
            'success' => true,
            'message' => __('ai.conversation_deleted'),
        ]);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $messages = $this->conversationService->listMessages(
            $conversation,
            (int) $request->integer('per_page', 30)
        );

        return response()->json([
            'success' => true,
            'message' => __('ai.messages_retrieved'),
            'data' => MessageResource::collection($messages)->response()->getData(true),
        ]);
    }

    public function storeMessage(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('sendMessage', $conversation);

        try {
            $result = $this->conversationService->sendMessage(
                $request->user(),
                $conversation,
                $request->validated('message')
            );

            return response()->json([
                'success' => true,
                'message' => __('ai.message_sent'),
                // Flat fields mirror POST /api/speech-to-text's response shape,
                // so the chat screen reads the assistant's reply the same way
                // whether the patient typed or spoke this turn.
                'question' => $result['assistant_message']?->message,
                'detected_symptoms' => $result['patient_message']->detected_symptoms,
                'finished' => $result['finished'],
                'emergency_detected' => $result['emergency_detected'],
                'risk_level' => $result['risk_level'],
                'red_flags' => $result['red_flags'],
                'recommended_action' => $result['recommended_action'],
                'assessment' => $result['assessment'],
                'data' => [
                    'patient_message' => new MessageResource($result['patient_message']),
                    'assistant_message' => $result['assistant_message']
                        ? new MessageResource($result['assistant_message'])
                        : null,
                ],
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500);
        }
    }

  
    public function storeHealixMessage(
        StoreMessageRequest $request,
        Conversation $conversation,
        HealixConversationService $healixConversation
    ): JsonResponse {
        set_time_limit(120);
        $this->authorize('sendMessage', $conversation);

        $result = $healixConversation->sendMessage(
            $request->user(),
            $conversation,
            $request->validated('message')
        );

        return response()->json([
            'success' => true,
            'message' => __('ai.message_sent'),
            // Flat fields for the frontend, same "flat fields + full
            // data.* resources" shape storeMessage() above already uses.
            'reply' => $result['reply'],
            'stage' => $result['stage'],
            // Safety-critical — check 'available' first: when it's false
            // (the Python service was unreachable this turn), these are
            // neutral not-triggered defaults, not a real safety verdict —
            // see HealixConversationService::sendMessage()'s own comment.
            'is_crisis' => $result['is_crisis'],
            'severity' => $result['severity'],
            'red_flags' => $result['red_flags'],
            'diagnosis' => $result['diagnosis'],
            'specialty' => $result['specialty'],
            'reports' => $result['reports'],
            'available' => $result['available'],
            'assessment_id' => $result['assessment_id'] ?? null,
            'data' => [
                'patient_message' => new MessageResource($result['patient_message']),
                'assistant_message' => new MessageResource($result['assistant_message']),
            ],
        ], 201);
    }
}
