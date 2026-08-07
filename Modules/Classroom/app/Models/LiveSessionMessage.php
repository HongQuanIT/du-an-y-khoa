<?php

declare(strict_types=1);

namespace Modules\Classroom\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Classroom\Enums\MessageType;

/**
 * @property int $id
 * @property int $live_session_id
 * @property int $user_id
 * @property string $body
 * @property MessageType $type
 * @property bool $is_hidden
 */
class LiveSessionMessage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'live_session_id',
        'user_id',
        'body',
        'type',
        'is_hidden',
        'is_pinned',
        'created_at',
    ];

    protected $casts = [
        'type' => MessageType::class,
        'is_hidden' => 'boolean',
        'is_pinned' => 'boolean',
        'created_at' => 'datetime',
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
}
