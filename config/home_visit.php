<?php

return [

    /*
        Scheduling conflict buffer (minutes).

        A pending request is hidden from a care provider's "nearby requests"
        list, and acceptance is rejected, if its scheduled_at falls within
        this many minutes of one of that provider's own already-booked
        (accepted/in_progress) visits — leaves room for travel between
        patients plus the session itself.
    */
    'conflict_buffer_minutes' => env('HOME_VISIT_CONFLICT_BUFFER_MINUTES', 60),

];
