<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\QuestionBank\Enums\EditorSubmitOutcome;
use Modules\QuestionBank\Enums\QuestionWorkflowEventType;

class QuestionWorkflowEvent extends Model
{
    protected $fillable = [
        'question_id',
        'review_cycle',
        'published_version',
        'event_type',
        'actor_id',
        'actor_role',
        'note',
        'outcome',
        'outcome_source',
        'outcome_by',
        'outcome_at',
        'outcome_note',
        'content_fingerprint',
        'meta',
        'occurred_at',
    ];

    protected $casts = [
        'event_type' => QuestionWorkflowEventType::class,
        'outcome' => EditorSubmitOutcome::class,
        'review_cycle' => 'integer',
        'published_version' => 'integer',
        'meta' => 'array',
        'occurred_at' => 'datetime',
        'outcome_at' => 'datetime',
    ];

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function outcomeBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'outcome_by');
    }
}
