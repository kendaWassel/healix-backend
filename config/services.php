<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'traccar_sms' => [
        'url'   => env('TRACCER_SMS_URL', 'https://www.traccar.org/sms/'),
        'api_key' => env('TRACCER_SMS_API_KEY'),

    ],
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

        // The charge currency is a business decision, not a client one — the
        // client used to send it, which let a caller pay in a weaker currency.
        'currency' => env('STRIPE_CURRENCY', 'usd'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
    ],

    // Single unified Medical Assistant AI service (Whisper + MARBERT + Interview + LLM).
    'medical_assistant' => [
        'url' => env('MEDICAL_ASSISTANT_SERVICE_URL', 'http://127.0.0.1:8000'),
        'timeout' => env('MEDICAL_ASSISTANT_SERVICE_TIMEOUT', 60),
        'retries' => env('MEDICAL_ASSISTANT_SERVICE_RETRIES', 3),
        'api_key' => env('MEDICAL_ASSISTANT_SERVICE_API_KEY'),
    ],

    'ddi' => [
        'url' => env('DDI_SERVICE_URL', 'http://127.0.0.1:8002'),
        'timeout' => env('DDI_SERVICE_TIMEOUT', 60),
        'retries' => env('DDI_SERVICE_RETRIES', 3),
        'api_key' => env('DDI_SERVICE_API_KEY'),
    ],

    'lab' => [
        'url' => env('LAB_SERVICE_URL', 'http://127.0.0.1:8000'),
        'timeout' => env('LAB_SERVICE_TIMEOUT', 120),
        'retries' => env('LAB_SERVICE_RETRIES', 2),
        'api_key' => env('LAB_SERVICE_API_KEY'),
    ],

    // Medical Knowledge & Clinical Guidance Service (FastAPI: Groq LLM +
    // WHO/CDC/NHS RAG + deterministic safety floor). Arabic + English only.
    'clinical_guidance' => [
        'url' => env('CLINICAL_GUIDANCE_SERVICE_URL', 'http://127.0.0.1:8001'),
        'timeout' => env('CLINICAL_GUIDANCE_SERVICE_TIMEOUT', 60),
        'retries' => env('CLINICAL_GUIDANCE_SERVICE_RETRIES', 2),
        'api_key' => env('CLINICAL_GUIDANCE_SERVICE_API_KEY'),
    ],

    // Healix AI (Arabic symptom-triage) FastAPI microservice — LangGraph +
    // RAG differential + crisis/red-flag safety layer. api_key here is the
    // shared secret sent as X-Healix-Internal-Token (HealixAiClient
    // overrides $apiKeyHeader — that service is not internet-facing and
    // rejects requests with a missing/wrong header, unlike the other
    // services above which use FastApiClient's default X-API-KEY or none).
    'healix' => [
        'url' => env('HEALIX_SERVICE_URL', 'http://127.0.0.1:8004'),
        // Must stay ABOVE ConversationController::storeHealixMessage()'s
        // set_time_limit(120) — a Guzzle timeout shorter than the PHP
        // script's own execution ceiling can fire early on a legitimately
        // slow (not hung) turn, throw ConnectionException, and trigger a
        // client-side retry against a request the Python side may still
        // be genuinely processing. 130 keeps PHP's own limit as the real,
        // final ceiling; Guzzle's timeout exists as backup, not the
        // binding constraint. See storeHealixMessage()'s own comment for
        // the full reasoning, including why the fully-pathological
        // worst case (up to 5 sequential LLM calls x healix_ai's own
        // HEALIX_LLM_TOTAL_BUDGET_SECONDS=60 each = 300s) isn't the
        // target this is sized against.
        'timeout' => env('HEALIX_SERVICE_TIMEOUT', 130),
        'retries' => env('HEALIX_SERVICE_RETRIES', 3),
        'api_key' => env('HEALIX_SERVICE_API_KEY'),
    ],

];
