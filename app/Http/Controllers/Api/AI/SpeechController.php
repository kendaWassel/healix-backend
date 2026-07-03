<?php

namespace App\Http\Controllers\Api\AI;

use App\Exceptions\AI\AIServiceException;
use App\Exceptions\AI\AudioStorageException;
use App\Http\Controllers\Controller;
use App\Http\Requests\TranscribeSpeechRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SpeechController extends Controller
{
    public function __construct(
        protected AIService $aiService
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
                'message' => 'You are not authorized to send messages in this conversation.',
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
            $detectedSymptoms = $this->aiService->extractSymptoms($transcribedText);

            $message->update([
                'transcribed_text' => $transcribedText,
                'detected_symptoms' => $detectedSymptoms,
                'status' => Message::STATUS_TRANSCRIBED,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Speech converted successfully.',
                'message_id' => $message->id,
                'text' => $transcribedText,
                'detected_symptoms' => $detectedSymptoms,
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
                'message' => 'An unexpected error occurred during speech processing.',
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
