<?php

return [

    /*
        Initial Broadcast Radius
    */
    'default_radius_km' => env('DELIVERY_DEFAULT_RADIUS_KM', 15),

    /*
        Radius Expansion Sequence
        Example:
        15 km -> 20 km -> 30 km -> 40 km
    */
    'radius_expansion' => array_map(
        'intval',
        explode(',', env('DELIVERY_RADIUS_EXPANSION', '15,20,30,40'))
    ),

    /*
         Maximum Broadcast Radius
    */
    'max_radius_km' => env('DELIVERY_MAX_RADIUS_KM', 40),

    /*
        Candidate Timeout (seconds)
    */
    'broadcast_timeout_seconds' => env(
        'DELIVERY_BROADCAST_TIMEOUT_SECONDS',
        30
    ),

    /*
        Delay Before Expanding Search Radius (seconds)
        
    */
    'broadcast_expansion_delay_seconds' => env(
        'DELIVERY_BROADCAST_EXPANSION_DELAY_SECONDS',
        30
    ),

];