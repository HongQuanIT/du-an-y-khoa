<?php

return [
    'name' => 'AiAssistant',

    /*
    | Client driver: `openai` for the real provider, `fake` for a deterministic
    | canned tutor (used automatically when no API key is present or in tests).
    */
    'driver' => env('AI_TUTOR_DRIVER'), // null → auto-resolve (openai if key set, else fake)

    'tutor_model' => env('AI_TUTOR_MODEL', 'gpt-4.1'),
    'guardrail_model' => env('AI_TUTOR_GUARDRAIL_MODEL', 'gpt-4.1-mini'),

    'max_output_tokens' => (int) env('AI_TUTOR_MAX_TOKENS', 900),
    'request_timeout' => (int) env('AI_TUTOR_TIMEOUT', 60),

    // Free-tier daily quota (Premium/entitlement `ai.tutor` is unlimited).
    'free_daily_limit' => (int) env('AI_TUTOR_FREE_DAILY_LIMIT', 10),

    // Idempotency window for auto-start thread/message dedupe (seconds).
    'idempotency_ttl' => (int) env('AI_TUTOR_IDEMPOTENCY_TTL', 120),

    // Horizon queue used for streaming replies.
    'queue' => env('AI_TUTOR_QUEUE', 'default'),
];
