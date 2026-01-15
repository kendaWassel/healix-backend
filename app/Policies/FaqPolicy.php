<?php

namespace App\Policies;

use App\Models\Faq;
use App\Models\User;

class FaqPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Faq $faq): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, Faq $faq): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, Faq $faq): bool
    {
        return $user->role === 'admin';
    }
}

