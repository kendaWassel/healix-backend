<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\User;

// User private channel - users can only access their own channel
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// User private channel (alternative format)
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Doctor private channel - only doctors can access their own channel
Broadcast::channel('doctor.{doctorId}', function ($user, $doctorId) {
    // Check if user is a doctor and the doctor ID matches
    if ($user->role !== 'doctor') {
        return false;
    }
    
    // Get doctor model and verify ID matches
    $doctor = $user->doctor;
    if (!$doctor) {
        return false;
    }
    
    return (int) $doctor->id === (int) $doctorId;
});

// Patient private channel - patients can only access their own channel
Broadcast::channel('patient.{patientId}', function ($user, $patientId) {
    // Check if user is a patient and the patient ID matches
    if ($user->role !== 'patient') {
        return false;
    }
    
    // Get patient model and verify ID matches
    $patient = $user->patient;
    if (!$patient) {
        return false;
    }
    
    return (int) $patient->id === (int) $patientId;
});

