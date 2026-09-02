<?php

return [
    'queue_enabled' => env('AUDIT_QUEUE_ENABLED', true),
    'queue' => env('AUDIT_QUEUE', 'audit'),

    'activity' => [
        'enabled' => env('AUDIT_ACTIVITY_ENABLED', true),
        'heartbeat_seconds' => (int) env('AUDIT_ACTIVITY_HEARTBEAT_SECONDS', 120),
        'idle_seconds' => (int) env('AUDIT_ACTIVITY_IDLE_SECONDS', 300),
        'redis_connection' => env('AUDIT_ACTIVITY_REDIS_CONNECTION', 'default'),
    ],

    'retention' => [
        'hot_days' => (int) env('AUDIT_HOT_RETENTION_DAYS', 180),
        'archive_disk' => env('AUDIT_ARCHIVE_DISK', 'local'),
        'archive_path' => env('AUDIT_ARCHIVE_PATH', 'audit-archives'),
    ],
];
