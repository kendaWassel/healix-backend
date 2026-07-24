<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | Locales the API will honour when negotiating the Accept-Language header.
    | Anything outside this list falls back to the default locale below.
    |
    */

    'supported' => ['en', 'ar'],

    /*
    |--------------------------------------------------------------------------
    | Default Locale
    |--------------------------------------------------------------------------
    |
    | Used when the client sends no Accept-Language header, or sends one that
    | matches none of the supported locales.
    |
    */

    'default' => env('APP_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Query Parameter Override
    |--------------------------------------------------------------------------
    |
    | Optional convenience override (e.g. /api/faqs?lang=ar). Useful for
    | testing and for links opened outside the app (verification emails,
    | PDF downloads) where the client cannot set a header. Set to null to
    | disable and rely on Accept-Language exclusively.
    |
    */

    'query_parameter' => 'lang',

    /*
    |--------------------------------------------------------------------------
    | Text Direction
    |--------------------------------------------------------------------------
    |
    | Exposed to Blade views and the Content-Language negotiation so the
    | frontend can mirror layouts without hardcoding a locale list.
    |
    */

    'direction' => [
        'en' => 'ltr',
        'ar' => 'rtl',
    ],

];
