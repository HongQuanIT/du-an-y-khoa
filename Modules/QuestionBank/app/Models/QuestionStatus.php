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
 * @property Carbon|null $last_attempt_at
 * @property Carbon|null $last_correct_at
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
        'last_attempt_at',
        'last_correct_at',
    ];

    protected $casts = [
        'status' => UserQuestionStatus::class,
        'attempts_count' => 'integer',
        'last_attempt_at' => 'datetime',
        'last_correct_at' => 'datetime',
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
