<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\QuestionBank\Enums\InstructorReviewDecision;

class QuestionInstructorReview extends Model
{
    protected $fillable = [
        'question_id',
        'review_cycle',
        'instructor_id',
        'decision',
        'note',
        'content_fingerprint',
        'reviewed_at',
    ];

    protected $casts = [
        'decision' => InstructorReviewDecision::class,
        'review_cycle' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /** @return BelongsTo<User, $this> */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }
}
