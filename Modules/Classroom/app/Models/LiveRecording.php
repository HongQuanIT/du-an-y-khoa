<?php

declare(strict_types=1);

namespace Modules\Classroom\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Classroom\Enums\RecordingStatus;

/**
 * @property int $id
 * @property int $live_session_id
 * @property RecordingStatus $status
 */
class LiveRecording extends Model
{
    protected $fillable = [
        'live_session_id',
        'media_id',
        'duration_seconds',
        'status',
        'egress_id',
        'playback_url',
        'hls_manifest',
    ];

    protected $casts = [
        'status' => RecordingStatus::class,
        'duration_seconds' => 'integer',
    ];

    /** @return BelongsTo<LiveSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class, 'live_session_id');
    }
}
