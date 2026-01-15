<?php

namespace App\Policies;

use App\Models\Upload;
use App\Models\User;

class UploadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function view(User $user, Upload $upload): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        return $upload->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['patient', 'doctor', 'pharmacist', 'care_provider', 'admin']);
    }

    public function update(User $user, Upload $upload): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        return $upload->user_id === $user->id;
    }

    public function delete(User $user, Upload $upload): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        return $upload->user_id === $user->id;
    }
}

