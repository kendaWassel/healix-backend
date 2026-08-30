<?php

namespace App\Http\Controllers\Api\AI;

use App\Exceptions\AI\AIServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SynthesizeHealixSpeechRequest;
use App\Http\Requests\TranscribeHealixSpeechRequest;
use App\Models\Conversation;
use App\Services\Healix\HealixAiClient;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Relays the Healix AI service's two speech endpoints
 * (POST /speech/transcribe, POST /speech/synthesize — api/main.py on that
 * side) to the frontend. A thin relay, deliberately, mirroring
 * api/main.py's own module docstring: "A voice turn is therefore always
 * two calls from a caller's point of view: POST /speech/transcribe to get
 * text, then POST /chat with that text — never a single combined 'voice
 * chat' endpoint." transcribe() below returns text ONLY; the frontend
 * sends that text through the existing
 * POST /conversations/{conversation}/healix-messages route
 * (ConversationController::storeHealixMessage()) as an ordinary turn —
 * this controller does not itself create a Message row or call
 * HealixConversationService, on purpose, so there is exactly one place a
 * patient turn gets persisted, not two competing ones.
 *
 * A deliberately separate class from Api\AI\SpeechController: that one is
 * hardwired to the OTHER AI backend (MedicalAssistantService's
 * Whisper/MARBERT interview engine, config('services.medical_assistant'))
 * and its own single-call transcribe-then-advance-the-interview contract
 * — a genuinely different flow, not something to branch inside.
 */
class HealixSpeechController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected HealixAiClient $healix
    ) {}

    /**
     * POST /api/patient/conversations/{conversation}/healix-speech/transcribe
     *
     * Gated by the SAME 'sendMessage' policy storeHealixMessage() uses —
     * transcribing audio into a conversation is authorized the same way
     * as sending a message to it, since that's what the returned text is
     * for. Laravel's own incoming field is 'audio' (this project's
     * existing speech-upload convention, e.g. TranscribeSpeechRequest);
     * HealixAiClient::transcribe() is what renames it to 'file' for the
     * Python side.
     */
    public function transcribe(TranscribeHealixSpeechRequest $request, Conversation $conversation): JsonResponse
    {
        set_time_limit(120);
        $this->authorize('sendMessage', $conversation);

        $audio = $request->file('audio');

        try {
            $result = $this->healix->transcribe(
                $audio->get(),
                $audio->getClientOriginalName() ?: 'recording.' . ($audio->getClientOriginalExtension() ?: 'webm')
            );
        } catch (AIServiceException $e) {
            Log::error('Healix speech transcription failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        }

        return response()->json([
            'success' => true,
            'message' => __('ai.healix_speech_transcribed'),
            'text' => (string) ($result['text'] ?? ''),
        ]);
    }

    /**
     * POST /api/patient/healix-speech/synthesize
     *
     * Not conversation-scoped, unlike transcribe() above — synthesizing
     * arbitrary text (typically a prior healix-messages reply the caller
     * already received) doesn't read or write conversation data, so there
     * is no conversation ownership to check; the route group's own
     * auth:sanctum + role:patient middleware is the only gate needed.
     * Returns raw audio/mpeg bytes, not JSON — mirrors api/main.py's own
     * POST /speech/synthesize response shape exactly, so the frontend
     * handles this response identically to a direct call to that Python
     * route would look, modulo the auth boundary now being Laravel's.
     */
    public function synthesize(SynthesizeHealixSpeechRequest $request): JsonResponse|Response
    {
        // 60 seconds is plenty for a single text-to-speech call, and the
        set_time_limit(60);

        try {
            $audioBytes = $this->healix->synthesize($request->validated('text'));
        } catch (AIServiceException $e) {
            Log::error('Healix speech synthesis failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        }

        return response($audioBytes, 200)->header('Content-Type', 'audio/mpeg');
    }
}
