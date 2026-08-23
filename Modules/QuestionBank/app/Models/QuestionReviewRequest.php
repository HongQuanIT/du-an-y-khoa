<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\QuestionBank\Enums\QuestionReviewAction;
use Modules\QuestionBank\Enums\QuestionReviewStatus;

class QuestionReviewRequest extends Model
{
    protected $fillable = [
        'question_id',
        'action',
        'payload',
        'status',
        'requested_by',
        'reviewed_by',
        'review_note',
        'reviewed_at',
    ];

    protected $casts = [
        'action' => QuestionReviewAction::class,
        'status' => QuestionReviewStatus::class,
        'payload' => 'array',
        'reviewed_at' => 'datetime',
    ];

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
