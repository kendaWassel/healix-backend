<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use App\Services\MedicalAssistant\MedicalAssistantService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ConversationService
{
    public function __construct(
        protected MedicalAssistantService $assistant
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
     * Persist a patient message and obtain the AI interview's next question.
     *
     * Delegates to the MedicalAssistantService orchestrator, which stores the
     * patient message, the AI question, extracted symptoms, the session id and
     * interview status.
     *
     * @return array{patient_message: \App\Models\Message, assistant_message: \App\Models\Message|null, finished: bool}
     */
    public function sendMessage(User $user, Conversation $conversation, string $messageText): array
    {
        $turn = $this->assistant->handleTextMessage($conversation, $messageText, $user->id);

        return [
            'patient_message' => $turn['patient_message'],
            'assistant_message' => $turn['assistant_message'],
            'finished' => $turn['result']['finished'],
        ];
    }

    protected function resolvePatient(User $user)
    {
        $patient = $user->patient;

        if (! $patient) {
            throw new \RuntimeException(__('messages.patient_profile_not_found'), 404);
        }

        return $patient;
    }
}
