<?php

return [
    'name' => 'AiAssistant',

    /*
    | Client driver: `openai` for the real provider, `fake` for a deterministic
    | canned tutor (used automatically when no API key is present or in tests).
    */
    'driver' => env('AI_TUTOR_DRIVER'), // null → auto-resolve (openai if key set, else fake)

    'tutor_model' => env('AI_TUTOR_MODEL', 'gpt-4.1-mini'),
    'guardrail_model' => env('AI_TUTOR_GUARDRAIL_MODEL', 'gpt-4.1-mini'),

    'max_output_tokens' => (int) env('AI_TUTOR_MAX_TOKENS', 900),
    'request_timeout' => (int) env('AI_TUTOR_TIMEOUT', 60),

    // Free-tier daily quota (learners without ai.tutor).
    'free_daily_limit' => (int) env('AI_TUTOR_FREE_DAILY_LIMIT', 10),

    // Premium soft-cap (entitlement ai.tutor, non-staff). Staff stays unlimited.
    'premium_daily_limit' => (int) env('AI_TUTOR_PREMIUM_DAILY_LIMIT', 100),

    // Max prior user+assistant messages sent to the model (newest kept).
    'history_max_messages' => (int) env('AI_TUTOR_HISTORY_MAX_MESSAGES', 8),

    // Shared Redis/file cache of identical auto-start replies.
    'response_cache' => filter_var(env('AI_TUTOR_RESPONSE_CACHE', true), FILTER_VALIDATE_BOOL),
    'response_cache_ttl' => (int) env('AI_TUTOR_RESPONSE_CACHE_TTL', 604800),

    // Idempotency window for auto-start thread/message dedupe (seconds).
    'idempotency_ttl' => (int) env('AI_TUTOR_IDEMPOTENCY_TTL', 120),

    // Horizon queue used for streaming replies.
    'queue' => env('AI_TUTOR_QUEUE', 'default'),
];
