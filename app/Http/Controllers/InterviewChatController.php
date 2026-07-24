<?php

namespace App\Http\Controllers;

use App\Exceptions\AI\AIServiceException;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Patient;
use App\Services\AI\AIService;
use App\Services\MedicalAssistant\MedicalAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Blade test harness for the history-taking interview.
 *
 * Uses the SAME pipeline as the Flutter API (MedicalAssistantService against a
 * persisted Conversation), plus voice and full chat history — all through
 * unauthenticated web routes intended only for local testing. Everything is
 * stored in the database; the current conversation id lives in the session.
 */
class InterviewChatController extends Controller
{
    private const SESSION_KEY = 'interview_conversation_id';

    public function __construct(
        private readonly MedicalAssistantService $assistant,
        private readonly AIService $ai,
    ) {}

    public function show(): View
    {
        return view('interview.chat');
    }

    /** One text turn. */
    public function message(Request $request): JsonResponse
    {
        $validated = $request->validate(['text' => ['required', 'string', 'max:2000']]);

        $conversation = $this->resolveConversation($request, create: true);
        if ($conversation === null) {
            return response()->json(['ok' => false, 'error' => __('ai.interview_no_patient')], 422);
        }

        try {
            $turn = $this->assistant->handleTextMessage(
                $conversation,
                $validated['text'],
                $conversation->patient?->user_id
            );
        } catch (AIServiceException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], $this->httpStatus($e));
        }

        return response()->json([
            'ok' => true,
            'conversation_id' => $conversation->id,
            'finished' => $turn['result']['finished'],
            'question' => $turn['assistant_message']?->message,
            'symptoms' => $turn['result']['symptoms'],
        ]);
    }

    /** One voice turn: upload -> Whisper -> interview continues -> persist. */
    public function voice(Request $request): JsonResponse
    {
        set_time_limit(180);

        $request->validate(['audio' => ['required', 'file', 'max:25600']]);

        $conversation = $this->resolveConversation($request, create: true);
        if ($conversation === null) {
            return response()->json(['ok' => false, 'error' => __('ai.interview_no_patient')], 422);
        }

        $userId = $conversation->patient?->user_id;
        $audio = $request->file('audio');
        $extension = $audio->getClientOriginalExtension() ?: 'webm';
        $audioPath = $audio->storeAs('chat-audio', Str::uuid() . '.' . $extension, 'public');

        $voiceMessage = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userId,
            'sender' => Message::SENDER_PATIENT,
            'message_type' => Message::TYPE_VOICE,
            'audio_path' => $audioPath,
            'status' => Message::STATUS_UPLOADED,
        ]);

        try {
            $text = $this->ai->speechToText(Storage::disk('public')->path($audioPath));
            $voiceMessage->update([
                'transcribed_text' => $text,
                'status' => Message::STATUS_TRANSCRIBED,
            ]);
            $turn = $this->assistant->handleVoiceMessage($conversation, $voiceMessage, $text, $userId);
        } catch (AIServiceException $e) {
            $voiceMessage->update(['status' => Message::STATUS_FAILED]);

            return response()->json(['ok' => false, 'error' => $e->getMessage()], $this->httpStatus($e));
        }

        return response()->json([
            'ok' => true,
            'conversation_id' => $conversation->id,
            'transcription' => $text,
            'finished' => $turn['result']['finished'],
            'question' => $turn['assistant_message']?->message,
            'symptoms' => $turn['result']['symptoms'],
        ]);
    }

    /** Messages of the current conversation (for rendering on load). */
    public function history(Request $request): JsonResponse
    {
        $conversation = $this->resolveConversation($request, create: false);

        return response()->json([
            'ok' => true,
            'conversation_id' => $conversation?->id,
            'messages' => $conversation ? $this->messagesPayload($conversation) : [],
        ]);
    }

    /** List of the test patient's past conversations (the chat history sidebar). */
    public function conversations(Request $request): JsonResponse
    {
        $patient = Patient::first();
        if ($patient === null) {
            return response()->json(['ok' => true, 'conversations' => []]);
        }

        $currentId = $request->session()->get(self::SESSION_KEY);
        $items = $patient->conversations()
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->map(fn (Conversation $c) => [
                'id' => $c->id,
                'title' => $c->title ?: ('محادثة #' . $c->id),
                'status' => $c->status,
                'messages' => $c->messages_count,
                'current' => (int) $c->id === (int) $currentId,
            ])->all();

        return response()->json(['ok' => true, 'conversations' => $items]);
    }

    /** Switch the current conversation and return its messages. */
    public function select(Request $request, Conversation $conversation): JsonResponse
    {
        $request->session()->put(self::SESSION_KEY, $conversation->id);

        return response()->json([
            'ok' => true,
            'conversation_id' => $conversation->id,
            'messages' => $this->messagesPayload($conversation),
        ]);
    }

    /** Start a fresh conversation on the next message. */
    public function reset(Request $request): JsonResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return response()->json(['ok' => true]);
    }

    // ------------------------------------------------------------------
    private function resolveConversation(Request $request, bool $create): ?Conversation
    {
        $id = $request->session()->get(self::SESSION_KEY);
        if ($id !== null) {
            $existing = Conversation::find($id);
            if ($existing !== null) {
                return $existing;
            }
        }

        if (! $create) {
            return null;
        }

        $patient = Patient::first();
        if ($patient === null) {
            return null;
        }

        $conversation = Conversation::create([
            'patient_id' => $patient->id,
            'title' => 'محادثة ' . now()->format('Y-m-d H:i'),
            'started_at' => now(),
        ]);
        $request->session()->put(self::SESSION_KEY, $conversation->id);

        return $conversation;
    }

    /** @return array<int, array{role: string, type: string, text: string}> */
    private function messagesPayload(Conversation $conversation): array
    {
        return $conversation->messages()->orderBy('id')->get()->map(fn (Message $m) => [
            'role' => $m->sender === Message::SENDER_ASSISTANT ? 'bot' : 'user',
            'type' => $m->message_type,
            'text' => (string) ($m->message ?: $m->transcribed_text ?: ''),
        ])->all();
    }

    private function httpStatus(AIServiceException $e): int
    {
        return $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 502;
    }
}
