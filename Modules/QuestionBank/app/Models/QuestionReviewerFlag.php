<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\QuestionBank\Enums\ReviewerFlag;

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
    ];

    protected $casts = [
        'flag' => ReviewerFlag::class,
        'review_cycle' => 'integer',
        'reviewed_at' => 'datetime',
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
