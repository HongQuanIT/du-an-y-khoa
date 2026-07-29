<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\QuestionBank\Database\Factories\QuestionAttemptFactory;

/**
 * A single answer event within a session (autosaved per question).
 *
 * @property int $id
 * @property string $session_id
 * @property int $user_id
 * @property string $question_id
 * @property array<int, int>|null $selected_option_ids
 * @property bool|null $is_correct
 * @property bool $used_hint
 * @property int $time_spent_seconds
 * @property string|null $confidence
 * @property bool $flagged
 * @property Carbon|null $answered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class QuestionAttempt extends Model
{
    /** @use HasFactory<QuestionAttemptFactory> */
    use HasFactory;

    protected $fillable = [
        'session_id',
        'user_id',
        'question_id',
        'selected_option_ids',
        'is_correct',
        'used_hint',
        'time_spent_seconds',
        'confidence',
        'flagged',
        'answered_at',
    ];

    protected $casts = [
        'selected_option_ids' => 'array',
        'is_correct' => 'boolean',
        'used_hint' => 'boolean',
        'flagged' => 'boolean',
        'time_spent_seconds' => 'integer',
        'answered_at' => 'datetime',
    ];

    /** @return BelongsTo<QuestionSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(QuestionSession::class, 'session_id');
    }

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

    protected static function newFactory(): QuestionAttemptFactory
    {
        return QuestionAttemptFactory::new();
    }
}
