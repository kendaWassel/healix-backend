<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Home Visits and Care Provider Sessions
    |--------------------------------------------------------------------------
    */

    'requested' => 'Home visit request created successfully.',
    'accepted' => 'Home visit accepted successfully',
    'follow_up_created' => 'Follow-up home visit created successfully.',
    'new_care_provider_requested' => 'New care provider requested successfully.',
    're_requested' => 'Home visit re-requested successfully.',

    'not_found' => 'Home visit not found.',
    'not_eligible_for_follow_up' => 'Original home visit not found or not eligible for follow-up.',

    'unauthorized_nurse' => 'Unauthorized or not a nurse.',
    'unauthorized_physiotherapist' => 'Unauthorized or not a physiotherapist.',

    /*
    | Sessions
    */

    'session_started' => 'Session started successfully',
    'session_ended' => 'Session ended successfully',

    'schedules_retrieved' => 'Schedules retrieved successfully.',
    'orders_retrieved' => 'Requests retrieved successfully.',
    'nearby_requests_retrieved' => 'Nearby requests retrieved successfully.',

    /*
    | Care provider workflow rules (NurseService / PhysiotherapistService)
    */

    'location_not_set' => 'Care provider location is not set.',
    'not_found_or_accepted' => 'Visit not found or already accepted.',
    'only_nurse_visits' => 'You can only accept nurse visits.',
    'only_physio_visits' => 'You can only accept physiotherapist visits.',
    'not_in_accepted_status' => 'Visit not found or not in accepted status.',
    'too_early_to_start' => 'Cannot start session before the scheduled time.',
    'not_in_progress' => 'Visit not found or not in progress.',
    'only_cancelled_re_request' => 'Only cancelled home visits can be re-requested with a new schedule time.',
    'time_conflict' => 'This visit time conflicts with one of your other booked visits.',

];
