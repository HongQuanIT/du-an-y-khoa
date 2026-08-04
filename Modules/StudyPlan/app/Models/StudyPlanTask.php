<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\QuestionBank\Models\Topic;
use Modules\StudyPlan\Database\Factories\StudyPlanTaskFactory;
use Modules\StudyPlan\Enums\TaskStatus;
use Modules\StudyPlan\Enums\TaskType;

/**
 * One day's piece of work inside a plan.
 *
 * `ref` carries the module payload: `{topic_ids, session_id, mode}` for
 * question/review tasks.
 *
 * @property int $id
 * @property int $study_plan_id
 * @property Carbon $date
 * @property TaskType $type
 * @property int $target
 * @property int $done
 * @property TaskStatus $status
 * @property array<string, mixed>|null $ref
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StudyPlanTask extends Model
{
    /** @use HasFactory<StudyPlanTaskFactory> */
    use HasFactory;

    protected $fillable = [
        'study_plan_id',
        'date',
        'type',
        'target',
        'done',
        'status',
        'ref',
    ];

    protected $casts = [
        'date' => 'date',
        'type' => TaskType::class,
        'status' => TaskStatus::class,
        'target' => 'integer',
        'done' => 'integer',
        'ref' => 'array',
    ];

    /** @return BelongsTo<StudyPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(StudyPlan::class, 'study_plan_id');
    }

    public function isDone(): bool
    {
        return $this->status === TaskStatus::Done;
    }

    public function isPending(): bool
    {
        return $this->status === TaskStatus::Pending;
    }

    public function isStarted(): bool
    {
        return $this->done > 0 || $this->sessionId() !== null;
    }

    /** Missed = a past pending task the learner never finished. */
    public function isMissed(): bool
    {
        return $this->isPending() && $this->date->lessThan(Carbon::today());
    }

    public function percent(): int
    {
        if ($this->target <= 0) {
            return $this->isDone() ? 100 : 0;
        }

        return (int) min(100, round($this->done / $this->target * 100));
    }

    public function sessionId(): ?string
    {
        $id = $this->ref['session_id'] ?? null;

        return is_string($id) ? $id : null;
    }

    /** @return array<int, int> */
    public function topicIds(): array
    {
        $ids = $this->ref['topic_ids'] ?? [];

        return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
    }

    /** Minutes budgeted for the task, using the ~2.25 min/question heuristic. */
    public function estimatedMinutes(): int
    {
        return max(5, (int) round($this->target * 2.25));
    }

    public function scopeLabel(): string
    {
        $names = array_values(array_intersect_key(self::topicNames(), array_flip($this->topicIds())));

        return $names === [] ? 'tổng hợp' : implode(', ', $names);
    }

    /** Human title used by the timeline, calendar and dashboard widget. */
    public function title(): string
    {
        return match ($this->type) {
            TaskType::Questions => "Làm {$this->target} câu {$this->scopeLabel()}",
            TaskType::Review => "Ôn lại {$this->target} câu sai",
            TaskType::Flashcards => "Ôn {$this->target} flashcard",
            TaskType::Read => "Đọc tài liệu {$this->scopeLabel()}",
        };
    }

    /**
     * Topic names are read on nearly every task render, so the (small) table
     * is loaded once per request instead of per task.
     *
     * @return array<int, string>
     */
    private static function topicNames(): array
    {
        return once(fn () => Topic::query()->pluck('name', 'id')->all());
    }

    protected static function newFactory(): StudyPlanTaskFactory
    {
        return StudyPlanTaskFactory::new();
    }
}
