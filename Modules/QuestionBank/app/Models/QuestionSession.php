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
 * @property array<string, array{note?: string, stem_html?: string, flagged?: bool, key_info_used?: bool, attending_tip_used?: bool}>|null $annotations
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
        'exam_id',
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

    /** @return HasMany<QuestionSessionSnapshot, $this> */
    public function snapshots(): HasMany
    {
        return $this->hasMany(QuestionSessionSnapshot::class, 'session_id')->orderBy('position');
    }

    public function displayName(): string
    {
        $name = ($this->filters ?? [])['name'] ?? null;

        if (is_string($name) && trim($name) !== '') {
            return trim($name);
        }

        return match ($this->source) {
            SessionSource::Custom => 'Phiên tùy chỉnh',
            SessionSource::WeakTopics => 'Ôn chủ đề yếu',
            SessionSource::StudyPlan => 'Kế hoạch học tập',
            SessionSource::Exam => 'Đề thi mô phỏng',
            SessionSource::SelfAssessment => 'Tự đánh giá',
        };
    }

    /**
     * @return array{unanswered: int, correct_with_hints: int, incorrect: int, correct: int}
     */
    public function repeatStatusCounts(): array
    {
        $counts = [
            'unanswered' => 0,
            'correct_with_hints' => 0,
            'incorrect' => 0,
            'correct' => 0,
        ];

        foreach ($this->repeatStatusMap() as $status) {
            $counts[$status]++;
        }

        return $counts;
    }

    /**
     * @param  array<int, string>  $statuses
     * @return array<int, string>
     */
    public function questionIdsForRepeat(array $statuses): array
    {
        $allowed = array_fill_keys($statuses, true);

        return collect($this->repeatStatusMap())
            ->filter(static fn (string $status): bool => isset($allowed[$status]))
            ->keys()
            ->values()
            ->all();
    }

    /** @return array<string, 'unanswered'|'correct_with_hints'|'incorrect'|'correct'> */
    private function repeatStatusMap(): array
    {
        $attempts = ($this->relationLoaded('attempts') ? $this->attempts : $this->attempts()->get())
            ->keyBy(static fn (QuestionAttempt $attempt): string => (string) $attempt->question_id);
        $statuses = [];

        foreach ($this->question_ids ?? [] as $questionId) {
            $attempt = $attempts->get((string) $questionId);
            $statuses[(string) $questionId] = match (true) {
                ! $attempt instanceof QuestionAttempt || $attempt->is_correct === null => 'unanswered',
                $attempt->is_correct && $attempt->used_hint => 'correct_with_hints',
                $attempt->is_correct === false => 'incorrect',
                default => 'correct',
            };
        }

        return $statuses;
    }

    protected static function newFactory(): QuestionSessionFactory
    {
        return QuestionSessionFactory::new();
    }
}
