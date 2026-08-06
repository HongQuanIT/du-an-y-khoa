<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\StudyPlan\Database\Factories\StudyPlanFactory;
use Modules\StudyPlan\Enums\PlanStatus;
use Modules\StudyPlan\Enums\PlanStrategy;
use Modules\StudyPlan\Enums\TaskStatus;

/**
 * A learner's plan towards an exam date: goals, scope and generated tasks.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $exam_key
 * @property Carbon $exam_target_date
 * @property int $daily_goal_questions
 * @property int $daily_goal_minutes
 * @property array<string, mixed>|array<int, int>|null $topic_scope
 * @property array<int, int>|null $study_days
 * @property PlanStrategy $strategy
 * @property PlanStatus $status
 * @property array<string, mixed>|null $progress_cache
 * @property Carbon|null $replanned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StudyPlan extends Model
{
    /** @use HasFactory<StudyPlanFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'exam_key',
        'exam_target_date',
        'daily_goal_questions',
        'daily_goal_minutes',
        'topic_scope',
        'study_days',
        'strategy',
        'status',
        'progress_cache',
        'replanned_at',
    ];

    protected $casts = [
        'exam_target_date' => 'date',
        'daily_goal_questions' => 'integer',
        'daily_goal_minutes' => 'integer',
        'topic_scope' => 'array',
        'study_days' => 'array',
        'strategy' => PlanStrategy::class,
        'status' => PlanStatus::class,
        'progress_cache' => 'array',
        'replanned_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<StudyPlanTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(StudyPlanTask::class);
    }

    /**
     * Tasks scheduled for today, ordered the way the panel renders them.
     *
     * @return HasMany<StudyPlanTask, $this>
     */
    public function todayTasks(): HasMany
    {
        return $this->tasks()
            ->whereDate('date', Carbon::today())
            ->orderBy('type')
            ->orderBy('id');
    }

    public function daysUntilExam(): int
    {
        return max(0, (int) Carbon::today()->diffInDays($this->exam_target_date, absolute: false));
    }

    /** 1-based index of today within the plan window, capped at the total. */
    public function currentDay(): int
    {
        $start = $this->created_at?->copy()->startOfDay() ?? Carbon::today();

        return min($this->totalDays(), (int) $start->diffInDays(Carbon::today()) + 1);
    }

    public function totalDays(): int
    {
        $start = $this->created_at?->copy()->startOfDay() ?? Carbon::today();

        return max(1, (int) $start->diffInDays($this->exam_target_date) + 1);
    }

    public function progressPercent(): int
    {
        return (int) ($this->progress_cache['percent'] ?? 0);
    }

    public function questionsDone(): int
    {
        return (int) ($this->progress_cache['questions_done'] ?? 0);
    }

    public function questionsTarget(): int
    {
        return (int) ($this->progress_cache['questions_target'] ?? 0);
    }

    public function isActive(): bool
    {
        return $this->status === PlanStatus::Active;
    }

    /** Whether a nightly adaptive replan touched the plan in the last day. */
    public function wasRecentlyReplanned(): bool
    {
        return $this->replanned_at !== null
            && $this->replanned_at->greaterThan(Carbon::now()->subDay());
    }

    /**
     * Topic ids used to pick questions. Supports the legacy flat list and the
     * richer scope payload written by the Amboss-style filter UI.
     *
     * @return array<int, int>
     */
    public function scopeTopicIds(): array
    {
        $scope = $this->topic_scope ?? [];

        if (isset($scope['topic_ids']) && is_array($scope['topic_ids'])) {
            return array_values(array_map('intval', $scope['topic_ids']));
        }

        if ($scope === [] || ! array_is_list($scope)) {
            return [];
        }

        return array_map('intval', $scope);
    }

    /**
     * Full Amboss-style filter bag stored inside `topic_scope`.
     *
     * @return array{
     *     topic_ids: array<int, int>,
     *     exam_tags: array<int, string>,
     *     articles: array<int, string>,
     *     symptoms: array<int, string>,
     *     saved_only: bool,
     *     difficulties: array<int, string>,
     *     difficulty: string|null,
     *     question_statuses: array<int, string>,
     *     question_status_mode: string
     * }
     */
    public function scopeFilters(): array
    {
        $scope = $this->topic_scope ?? [];
        $defaults = [
            'topic_ids' => [],
            'exam_tags' => [],
            'articles' => [],
            'symptoms' => [],
            'saved_only' => false,
            'difficulties' => [],
            'difficulty' => null,
            'question_statuses' => [],
            'question_status_mode' => 'latest',
        ];

        if (isset($scope['topic_ids']) || isset($scope['exam_tags'])) {
            $filters = array_merge($defaults, array_intersect_key($scope, $defaults), [
                'topic_ids' => $this->scopeTopicIds(),
                'saved_only' => (bool) ($scope['saved_only'] ?? false),
            ]);

            // Backward compatibility for plans written by the old single-select UI.
            if ($filters['question_statuses'] === [] && is_string($scope['question_status'] ?? null)) {
                $filters['question_statuses'] = [$scope['question_status']];
            }

            if ($filters['difficulties'] === [] && is_string($scope['difficulty'] ?? null)) {
                $filters['difficulties'] = [$scope['difficulty']];
            }

            $filters['difficulties'] = array_values(array_intersect(
                array_map('strval', (array) $filters['difficulties']),
                Difficulty::values(),
            ));

            return $filters;
        }

        return array_merge($defaults, ['topic_ids' => $this->scopeTopicIds()]);
    }

    /** @return array<int, int> ISO weekdays; empty scope means every day. */
    public function studyWeekdays(): array
    {
        $days = array_values(array_map('intval', $this->study_days ?? []));

        return $days === [] ? range(1, 7) : $days;
    }

    public function pendingTasksCount(): int
    {
        return $this->tasks()->where('status', TaskStatus::Pending)->count();
    }

    protected static function newFactory(): StudyPlanFactory
    {
        return StudyPlanFactory::new();
    }
}
