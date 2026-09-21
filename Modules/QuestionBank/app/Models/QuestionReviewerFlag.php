<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\QuestionBank\Enums\ReviewerFlag;
use Modules\QuestionBank\Enums\ReviewFlagOutcome;

class QuestionReviewerFlag extends Model
{
    protected $fillable = [
        'question_id',
        'review_cycle',
        'reviewer_id',
        'flag',
        'note',
        'content_fingerprint',
        'reviewed_at',
        'outcome',
        'outcome_source',
        'outcome_by',
        'outcome_at',
        'outcome_note',
    ];

    protected $casts = [
        'flag' => ReviewerFlag::class,
        'outcome' => ReviewFlagOutcome::class,
        'review_cycle' => 'integer',
        'reviewed_at' => 'datetime',
        'outcome_at' => 'datetime',
    ];

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
