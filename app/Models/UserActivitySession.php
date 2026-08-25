<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserActivitySession extends Model
{
    protected $fillable = [
        'user_id', 'session_id', 'area', 'portal', 'started_at', 'last_seen_at',
        'duration_seconds', 'heartbeat_count', 'ip', 'device_type', 'device_name',
        'operating_system', 'browser',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'duration_seconds' => 'integer',
            'heartbeat_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
