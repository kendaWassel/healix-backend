<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Consultation Module
    |--------------------------------------------------------------------------
    */

    'booked' => 'Consultation booked successfully.',
    'started' => 'Consultation started successfully.',
    'ended' => 'Consultation ended successfully.',
    'joined_started' => 'Joining already-started consultation.',

    'not_found' => 'Consultation not found.',
    'not_owned_by_doctor' => 'Consultation does not belong to this doctor.',
    'already_ended' => 'This consultation has already ended.',
    'cannot_start' => 'This consultation cannot be started yet.',

    'schedules_retrieved' => 'Schedules retrieved successfully.',
    'no_scheduled_consultations' => 'No scheduled consultations found',
    'no_scheduled_care_providers' => 'No scheduled care providers found',

    'slots_retrieved' => 'Available slots retrieved successfully.',
    'no_doctors_for_specialization' => 'No doctors available for this specialization yet.',

    'meet_link_failed' => 'Unable to create the video meeting link. Please try again.',

    /*
    | Booking rules (ConsultationService)
    */

    'scheduled_at_required' => 'A scheduled time is required for a scheduled consultation.',
    'scheduled_outside_hours' => "The scheduled time is outside the doctor's available hours",
    'scheduled_in_past' => 'The scheduled time cannot be in the past.',
    'doctor_hours_not_set' => 'Doctor availability hours are not set',
    'doctor_unavailable' => 'Doctor is not available now',
    'doctor_busy' => 'Doctor is currently busy with another consultation.',
    'slot_taken' => 'Requested time slot is already booked',
    'invalid_type' => 'Invalid consultation type.',
    'scheduled_time_missing' => 'Scheduled time is missing for this consultation.',
    'too_early' => 'It is not time to start the scheduled consultation yet.',
    'not_found_or_unauthorized' => 'Consultation not found or not authorized.',
    'not_in_progress' => 'Consultation is not in progress.',
    'not_found_for_doctor' => 'Consultation not found for this doctor.',

    /*
    | Doctor schedule generation (DoctorService)
    */

    'invalid_from_format' => 'Invalid start-time format: :error',
    'invalid_to_format' => 'Invalid end-time format: :error',
    'schedule_parse_failed' => 'Failed to build the schedule for the given date: :error',
    'invalid_schedule_order' => 'Invalid schedule: the start time must be before the end time',
    'no_slots_generated' => 'No time slots could be generated. Please check the working hours and interval.',

];
