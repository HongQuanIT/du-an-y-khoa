<?php

declare(strict_types=1);

namespace Modules\Analytics\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\QuestionBank\Models\Lesson;

/**
 * Rolled-up accuracy per lesson (bài học), feeding weak topics and adaptive replanning.
 *
 * @property int $id
 * @property int $user_id
 * @property int $lesson_id
 * @property int $attempts
 * @property int $correct
 * @property float $correct_rate
 * @property int $mastery_level
 * @property Carbon|null $last_activity_at
 * @property array<string, mixed>|null $trend
 */
class TopicMastery extends Model
{
    protected $table = 'topic_mastery';

    protected $fillable = [
        'user_id',
        'lesson_id',
        'attempts',
        'correct',
        'correct_rate',
        'mastery_level',
        'last_activity_at',
        'trend',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'correct' => 'integer',
        'correct_rate' => 'float',
        'mastery_level' => 'integer',
        'last_activity_at' => 'datetime',
        'trend' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
