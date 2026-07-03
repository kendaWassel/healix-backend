<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConversationRequest;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Services\ConversationService;
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
            'message' => 'Conversations retrieved successfully.',
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
                'message' => 'Conversation created successfully.',
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
            'message' => 'Conversation retrieved successfully.',
            'data' => new ConversationResource($conversation),
        ]);
    }

    public function destroy(Conversation $conversation): JsonResponse
    {
        $this->authorize('delete', $conversation);

        $this->conversationService->delete($conversation);

        return response()->json([
            'success' => true,
            'message' => 'Conversation deleted successfully.',
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
            'message' => 'Messages retrieved successfully.',
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
                'message' => 'Message sent successfully.',
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
}
