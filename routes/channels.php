<?php

use Illuminate\Support\Facades\Broadcast;



Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('doctor.{doctorId}', function ($user, $doctorId) {
    return $user->role === 'doctor'
        && optional($user->doctor)->id === (int) $doctorId;
});
