<?php

declare(strict_types=1);

namespace Modules\Analytics\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $user_id
 * @property Carbon $date
 * @property int $questions_answered
 * @property int $correct_answers
 * @property int $study_seconds
 * @property int $completed_sessions
 * @property bool $daily_goal_reached
 */
final class DailyLearningStat extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'questions_answered',
        'correct_answers',
        'study_seconds',
        'completed_sessions',
        'daily_goal_reached',
    ];

    protected $casts = [
        'date' => 'date',
        'questions_answered' => 'integer',
        'correct_answers' => 'integer',
        'study_seconds' => 'integer',
        'completed_sessions' => 'integer',
        'daily_goal_reached' => 'boolean',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
