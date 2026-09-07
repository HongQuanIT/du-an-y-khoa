<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\QuestionBank\Enums\UserQuestionStatus;

/**
 * Per-user progress state for a single question.
 *
 * @property int $id
 * @property int $user_id
 * @property string $question_id
 * @property UserQuestionStatus $status
 * @property int $attempts_count
 * @property int $coverage_count
 * @property int $incorrect_count
 * @property int $correct_streak
 * @property int $mastery_score
 * @property int $review_interval_days
 * @property float $ease_factor
 * @property bool $last_used_hint
 * @property Carbon|null $last_attempt_at
 * @property Carbon|null $last_correct_at
 * @property Carbon|null $next_review_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class QuestionStatus extends Model
{
    protected $table = 'question_status';

    protected $fillable = [
        'user_id',
        'question_id',
        'status',
        'attempts_count',
        'coverage_count',
        'incorrect_count',
        'correct_streak',
        'mastery_score',
        'review_interval_days',
        'ease_factor',
        'last_used_hint',
        'last_attempt_at',
        'last_correct_at',
        'next_review_at',
    ];

    protected $casts = [
        'status' => UserQuestionStatus::class,
        'attempts_count' => 'integer',
        'coverage_count' => 'integer',
        'incorrect_count' => 'integer',
        'correct_streak' => 'integer',
        'mastery_score' => 'integer',
        'review_interval_days' => 'integer',
        'ease_factor' => 'float',
        'last_used_hint' => 'boolean',
        'last_attempt_at' => 'datetime',
        'last_correct_at' => 'datetime',
        'next_review_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}
