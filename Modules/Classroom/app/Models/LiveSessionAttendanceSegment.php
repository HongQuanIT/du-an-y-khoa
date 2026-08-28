<?php

declare(strict_types=1);

namespace Modules\Classroom\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A single join-to-leave interval; reconnects create additional rows. */
final class LiveSessionAttendanceSegment extends Model
{
    protected $fillable = ['live_session_id', 'user_id', 'joined_at', 'left_at', 'metadata'];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<LiveSession, $this> */
    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
