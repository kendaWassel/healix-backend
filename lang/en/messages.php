<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Generic Application Messages
    |--------------------------------------------------------------------------
    |
    | Cross-cutting messages reused by many modules: generic success and
    | failure envelopes, authorization errors and validation wrappers.
    |
    */

    'success' => 'Operation completed successfully.',
    'failed' => 'Operation failed.',
    'unexpected_error' => 'An unexpected error occurred. Please try again.',
    'not_found' => 'The requested resource was not found.',
    'unauthorized' => 'Unauthorized.',
    'unauthenticated' => 'Unauthenticated.',
    'forbidden' => 'Forbidden: Access denied',

    'validation_failed' => 'Validation failed.',

    /*
    | Profile (shared by every role)
    */

    'profile_retrieved' => 'Profile retrieved successfully',
    'profile_updated' => 'Profile updated successfully',

    /*
    | Role profile lookups
    */

    'doctor_profile_not_found' => 'Doctor profile not found',
    'patient_profile_not_found' => 'Patient profile not found.',
    'pharmacist_profile_not_found' => 'Pharmacist profile not found.',
    'delivery_profile_not_found' => 'Delivery profile not found',
    'care_provider_profile_not_found' => 'Care provider profile not found.',
    'nurse_profile_not_found' => 'Care provider profile not found or not a nurse',
    'physiotherapist_profile_not_found' => 'Care provider profile not found or not a physiotherapist',

    'doctor_not_found' => 'Doctor not found.',
    'patient_not_found' => 'Patient not found.',
    'patient_not_found_for_user' => 'Patient not found for this user.',
    'pharmacist_not_found' => 'Pharmacist not found.',
    'pharmacy_not_found' => 'Pharmacy not found.',
    'care_provider_not_found' => 'Care provider not found.',
    'delivery_not_found' => 'Delivery not found.',
    'user_not_found' => 'User not found',

];
