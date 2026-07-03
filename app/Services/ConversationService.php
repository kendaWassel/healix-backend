<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\AI\AIService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ConversationService
{
    public function __construct(
        protected AIService $aiService
    ) {}

    public function listForPatient(User $user, int $perPage = 15): LengthAwarePaginator
    {
        $patient = $this->resolvePatient($user);

        return Conversation::query()
            ->where('patient_id', $patient->id)
            ->withCount('messages')
            ->with('latestMessage')
            ->latest()
            ->paginate($perPage);
    }

    public function create(User $user, array $data): Conversation
    {
        $patient = $this->resolvePatient($user);

        return Conversation::create([
            'patient_id' => $patient->id,
            'title' => $data['title'],
            'started_at' => now(),
        ]);
    }

    public function delete(Conversation $conversation): void
    {
        $conversation->delete();
    }

    public function listMessages(Conversation $conversation, int $perPage = 30): LengthAwarePaginator
    {
        return $conversation->messages()
            ->latest()
            ->paginate($perPage);
    }

    /**
     * @return array{patient_message: Message, assistant_message: Message|null}
     */
    public function sendMessage(User $user, Conversation $conversation, string $messageText): array
    {
        return DB::transaction(function () use ($user, $conversation, $messageText) {
            $patientMessage = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'sender' => Message::SENDER_PATIENT,
                'message_type' => Message::TYPE_TEXT,
                'message' => $messageText,
            ]);

            $assistantReply = $this->aiService->getMedicalAssistantResponse(
                $messageText,
                $conversation->id
            );

            $assistantMessage = null;

            if ($assistantReply !== null && trim($assistantReply) !== '') {
                $assistantMessage = Message::create([
                    'conversation_id' => $conversation->id,
                    'sender' => Message::SENDER_ASSISTANT,
                    'message_type' => Message::TYPE_TEXT,
                    'message' => $assistantReply,
                ]);
            }

            return [
                'patient_message' => $patientMessage,
                'assistant_message' => $assistantMessage,
            ];
        });
    }

    protected function resolvePatient(User $user)
    {
        $patient = $user->patient;

        if (! $patient) {
            throw new \RuntimeException('Patient profile not found.', 404);
        }

        return $patient;
    }
}
