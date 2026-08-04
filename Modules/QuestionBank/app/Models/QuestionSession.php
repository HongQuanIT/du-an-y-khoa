<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\QuestionBank\Database\Factories\QuestionSessionFactory;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Enums\SessionStatus;

/**
 * A practice/exam session grouping a batch of question attempts.
 *
 * @property string $id
 * @property int $user_id
 * @property SessionMode $mode
 * @property SessionStatus $status
 * @property SessionSource $source
 * @property array<string, mixed>|null $filters
 * @property array<int, string>|null $question_ids
 * @property int $total
 * @property int $answered_count
 * @property int $correct_count
 * @property int|null $time_limit_seconds
 * @property array<string, mixed>|null $paused_state
 * @property array<string, array{note?: string, stem_html?: string}>|null $annotations
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class QuestionSession extends Model
{
    /** @use HasFactory<QuestionSessionFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'mode',
        'status',
        'source',
        'filters',
        'question_ids',
        'total',
        'answered_count',
        'correct_count',
        'time_limit_seconds',
        'paused_state',
        'annotations',
    ];

    protected $casts = [
        'mode' => SessionMode::class,
        'status' => SessionStatus::class,
        'source' => SessionSource::class,
        'filters' => 'array',
        'question_ids' => 'array',
        'paused_state' => 'array',
        'annotations' => 'array',
        'total' => 'integer',
        'answered_count' => 'integer',
        'correct_count' => 'integer',
        'time_limit_seconds' => 'integer',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<QuestionAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuestionAttempt::class, 'session_id');
    }

    protected static function newFactory(): QuestionSessionFactory
    {
        return QuestionSessionFactory::new();
    }
}
