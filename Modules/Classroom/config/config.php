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
        'api_key' => env('LIVEKIT_API_KEY', ''),
        'api_secret' => env('LIVEKIT_API_SECRET', ''),
        'token_ttl_seconds' => (int) env('LIVEKIT_TOKEN_TTL', 3600),
        'egress_enabled' => (bool) env('LIVEKIT_EGRESS_ENABLED', false),
        'webhook_secret' => env('LIVEKIT_WEBHOOK_SECRET', ''),
        'egress_bucket' => env('LIVEKIT_EGRESS_BUCKET', env('AWS_BUCKET', '')),
        'egress_region' => env('LIVEKIT_EGRESS_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
        'egress_endpoint' => env('LIVEKIT_EGRESS_ENDPOINT', env('AWS_ENDPOINT', '')),
        'egress_access_key' => env('LIVEKIT_EGRESS_ACCESS_KEY', env('AWS_ACCESS_KEY_ID', '')),
        'egress_secret_key' => env('LIVEKIT_EGRESS_SECRET_KEY', env('AWS_SECRET_ACCESS_KEY', '')),
    ],
];
