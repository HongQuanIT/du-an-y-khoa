<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Immutable question content captured when a session is created. */
final class QuestionSessionSnapshot extends Model
{
    protected $fillable = [
        'session_id',
        'question_id',
        'position',
        'question_version',
        'payload',
    ];

    protected $casts = [
        'position' => 'integer',
        'question_version' => 'integer',
        'payload' => 'array',
    ];

    /** @return BelongsTo<QuestionSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(QuestionSession::class, 'session_id');
    }
}
