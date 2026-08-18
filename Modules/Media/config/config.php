<?php

declare(strict_types=1);

return [
    'name' => 'Media',
    'disk_public' => 'public',
    'disk_private' => 'local',
    'image_max_kb' => 10240,
    'video_max_kb' => 102400,
    'image_mimes' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    'video_mimes' => ['mp4', 'webm', 'mov'],
    'variants' => [
        'thumb' => 400,
        'lg' => 1920,
    ],
];
