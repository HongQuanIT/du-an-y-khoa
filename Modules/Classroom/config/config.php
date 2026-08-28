<?php

declare(strict_types=1);

return [
    'name' => 'Classroom',

    /*
    | When Billing entitlements are empty, allow any authenticated user to host
    | in local/dev so Classroom MVP is usable. Production should set false.
    */
    'open_hosting' => (bool) env('CLASSROOM_OPEN_HOSTING', env('APP_ENV') === 'local'),

    'livekit' => [
        'url' => env('LIVEKIT_URL', 'ws://127.0.0.1:7880'),
        'api_url' => env('LIVEKIT_API_URL', env('LIVEKIT_URL', 'ws://127.0.0.1:7880')),
        'api_key' => env('LIVEKIT_API_KEY', ''),
        'api_secret' => env('LIVEKIT_API_SECRET', ''),
        'token_ttl_seconds' => (int) env('LIVEKIT_TOKEN_TTL', 3600),
        'host_grace_seconds' => (int) env('LIVEKIT_HOST_GRACE_SECONDS', 300),
        'webhook_secret' => env('LIVEKIT_WEBHOOK_SECRET', ''),
    ],
];
