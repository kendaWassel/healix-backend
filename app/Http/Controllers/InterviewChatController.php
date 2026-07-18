<?php

namespace App\Http\Controllers;

use App\Exceptions\AI\AIServiceException;
use App\Models\Conversation;
use App\Models\Patient;
use App\Services\MedicalAssistant\MedicalAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Blade test harness for the history-taking interview.
 *
 * Uses the SAME pipeline as the Flutter API: MedicalAssistantService against a
 * persisted Conversation. Everything (messages, symptoms, session id, status,
 * turn numbers) is stored in the database — there is no separate, non-persisting
 * interview path anymore. The page attaches to a test patient and keeps the
 * conversation id in the Laravel session.
 */
class InterviewChatController extends Controller
{
    /** Laravel session key holding the DB conversation id for this browser. */
    private const SESSION_KEY = 'interview_conversation_id';

    public function __construct(
        private readonly MedicalAssistantService $assistant
    ) {}

    /** Render the chat test page. */
    public function show(): View
    {
        return view('interview.chat');
    }

    /** Handle one patient message through the persisting pipeline. */
    public function message(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
        ]);

        $conversation = $this->resolveConversation($request);
        if ($conversation === null) {
            return response()->json([
                'ok' => false,
                'error' => 'لا يوجد مريض في قاعدة البيانات لربط المحادثة التجريبية به.',
            ], 422);
        }

        try {
            $turn = $this->assistant->handleTextMessage(
                $conversation,
                $validated['text'],
                $conversation->patient?->user_id
            );
        } catch (AIServiceException $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 502;

            return response()->json(['ok' => false, 'error' => $e->getMessage()], $status);
        }

        $assistantMessage = $turn['assistant_message'];

        return response()->json([
            'ok' => true,
            'finished' => $turn['result']['finished'],
            'question' => $assistantMessage?->message,
            'symptoms' => $turn['result']['symptoms'],
        ]);
    }

    /** Start a fresh interview (a new persisted conversation on the next message). */
    public function reset(Request $request): JsonResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return response()->json(['ok' => true]);
    }

    /** Load the session's conversation, or create one for the test patient. */
    private function resolveConversation(Request $request): ?Conversation
    {
        $id = $request->session()->get(self::SESSION_KEY);
        if ($id !== null) {
            $existing = Conversation::find($id);
            if ($existing !== null) {
                return $existing;
            }
        }

        $patient = Patient::first();
        if ($patient === null) {
            return null;
        }

        $conversation = Conversation::create([
            'patient_id' => $patient->id,
            'title' => 'Blade Test Interview',
            'started_at' => now(),
        ]);
        $request->session()->put(self::SESSION_KEY, $conversation->id);

        return $conversation;
    }
}
