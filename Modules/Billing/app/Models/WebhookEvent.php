<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $provider
 * @property string $event_id
 * @property string $event_type
 * @property array<string, mixed> $payload
 * @property string $status
 * @property Carbon|null $processed_at
 * @property string|null $error_message
 */
class WebhookEvent extends Model
{
    protected $table = 'billing_webhook_events';

    protected $fillable = [
        'provider',
        'event_id',
        'event_type',
        'payload',
        'status',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
