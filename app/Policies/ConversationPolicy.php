<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'patient' && $user->patient !== null;
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $this->ownsConversation($user, $conversation);
    }

    public function create(User $user): bool
    {
        return $user->role === 'patient' && $user->patient !== null;
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $this->ownsConversation($user, $conversation);
    }

    public function sendMessage(User $user, Conversation $conversation): bool
    {
        return $this->ownsConversation($user, $conversation);
    }

    protected function ownsConversation(User $user, Conversation $conversation): bool
    {
        if ($user->role !== 'patient' || ! $user->patient) {
            return false;
        }

        return (int) $conversation->patient_id === (int) $user->patient->id;
    }
}
