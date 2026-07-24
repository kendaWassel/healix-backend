<?php

namespace App\Http\Controllers\Api\AI;

use App\Exceptions\AI\AIServiceException;
use App\Exceptions\AI\AudioStorageException;
use App\Http\Controllers\Controller;
use App\Http\Requests\TranscribeSpeechRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\AIService;
use App\Services\MedicalAssistant\MedicalAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SpeechController extends Controller
{
    public function __construct(
        protected AIService $aiService,
        protected MedicalAssistantService $assistant
    ) {}


    public function transcribe(TranscribeSpeechRequest $request): JsonResponse
    {
        set_time_limit(180);

        $user = $request->user();
        $conversationId = $request->validated('conversation_id');
        $audio = $request->file('audio');

        $conversation = Conversation::findOrFail($conversationId);

        if (! $this->userOwnsConversation($user, $conversation)) {
            return response()->json([
                'success' => false,
                'message' => __('ai.conversation_not_authorized'),
            ], 403);
        }
        Log::info('Speech upload started', [
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'mime' => $audio->getClientMimeType(),
            'size' => $audio->getSize(),
        ]);

        $message = null;

        try {
            $audioPath = $this->storeAudio($audio);

            Log::info('Speech upload finished', [
                'user_id' => $user->id,
                'conversation_id' => $conversationId,
                'audio_path' => $audioPath,
            ]);

            $message = Message::create([
                'conversation_id' => $conversationId,
                'sender_id' => $user->id,
                'sender' => 'patient',
                'message_type' => Message::TYPE_VOICE,
                'audio_path' => $audioPath,
                'status' => Message::STATUS_UPLOADED,
            ]);

            $absoluteAudioPath = Storage::disk('public')->path($audioPath);

            $transcribedText = $this->aiService->speechToText($absoluteAudioPath);

            $message->update([
                'transcribed_text' => $transcribedText,
                'status' => Message::STATUS_TRANSCRIBED,
            ]);

            // Continue the interview through the SAME pipeline as text messages:
            // the transcribed text drives the interview engine (MARBERT + next
            // question), reusing this voice message as the patient turn.
            $turn = $this->assistant->handleVoiceMessage(
                $conversation,
                $message,
                $transcribedText,
                $user->id
            );

            $assistantMessage = $turn['assistant_message'];

            return response()->json([
                'success' => true,
                'message' => __('ai.speech_converted'),
                'message_id' => $message->id,
                'text' => $transcribedText,
                'detected_symptoms' => $message->detected_symptoms,
                'finished' => $turn['result']['finished'],
                'question' => $assistantMessage?->message,
                'assistant_message_id' => $assistantMessage?->id,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (AudioStorageException $e) {
            Log::error('Speech audio storage failed', [
                'user_id' => $user->id,
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (AIServiceException $e) {
            $this->markMessageFailed($message);

            Log::error('AI service error during speech processing', [
                'user_id' => $user->id,
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Throwable $e) {
            $this->markMessageFailed($message);

            Log::error('Speech processing unexpected error', [
                'user_id' => $user->id,
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('ai.speech_unexpected_error'),
            ], 500);
        }
    }

    protected function storeAudio($audio): string
    {
        $extension = $audio->getClientOriginalExtension() ?: 'm4a';
        $filename = Str::uuid() . '.' . $extension;
        $directory = 'chat-audio';

        $path = $audio->storeAs($directory, $filename, 'public');

        if (! $path) {
            throw new AudioStorageException();
        }

        return $path;
    }

    protected function userOwnsConversation($user, Conversation $conversation): bool
    {
        $patient = $user->patient;

        if (! $patient) {
            return false;
        }

        return (int) $conversation->patient_id === (int) $patient->id;
    }

    protected function markMessageFailed(?Message $message): void
    {
        if ($message) {
            $message->update(['status' => Message::STATUS_FAILED]);
        }
    }
}
