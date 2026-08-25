<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_id', 'actor_id', 'actor_role', 'portal', 'category', 'result', 'session_id',
        'action', 'auditable_type', 'auditable_id', 'before', 'after', 'metadata',
        'ip', 'user_agent', 'device_type', 'device_name', 'operating_system',
        'browser', 'request_id', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Audit logs are immutable.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Audit logs are immutable.');
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
