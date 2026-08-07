<?php

declare(strict_types=1);

namespace Modules\Classroom\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $live_session_id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon $raised_at
 * @property \Illuminate\Support\Carbon|null $acknowledged_at
 */
class LiveSessionHand extends Model
{
    protected $fillable = [
        'live_session_id',
        'user_id',
        'raised_at',
        'acknowledged_at',
    ];

    protected $casts = [
        'raised_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    /** @return BelongsTo<LiveSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class, 'live_session_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->acknowledged_at === null;
    }
}
