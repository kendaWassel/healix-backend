<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Comma-separated list of allowed frontend origins, e.g.
    // FRONTEND_URLS="http://localhost:5173,https://your-frontend.vercel.app"
    'allowed_origins' => [
        'https://healix-admin-kappa.vercel.app',
        'http://localhost:5173',
        'https://healix-admin-only.vercel.app/',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Lets the browser frontend read back which locale the API actually served,
    // so it can detect a fallback (e.g. asked for "ar", received "en").
    'exposed_headers' => ['Content-Language'],

    'max_age' => 0,

    'supports_credentials' => true,

];