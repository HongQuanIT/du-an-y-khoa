<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\StudyPlan\Enums\TaskStatus;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Models\StudyPlanQuestion;
use Modules\StudyPlan\Models\StudyPlanTask;

/**
 * Shapes plan tasks for the detail timeline and calendar so the Blade views
 * stay presentation-only.
 */
final class PlanTimeline
{
    /**
     * Tasks grouped into weeks of days, in schedule order.
     *
     * @return array<int, array{index: int, title: string, progress: string, days: array<int, array<string, mixed>>}>
     */
    public function weeks(StudyPlan $plan): array
    {
        $tasks = $plan->tasks()->orderBy('date')->orderBy('id')->get();

        if ($tasks->isEmpty()) {
            return [];
        }

        $start = $tasks->first()->date->copy()->startOfWeek();
        $sessionIds = $tasks
            ->map(fn (StudyPlanTask $task): ?string => $task->sessionId())
            ->filter()
            ->unique()
            ->values();
        $attemptsBySession = QuestionAttempt::query()
            ->whereIn('session_id', $sessionIds)
            ->whereNotNull('is_correct')
            ->orderByDesc('answered_at')
            ->orderByDesc('id')
            ->get(['id', 'session_id', 'question_id', 'is_correct', 'used_hint', 'answered_at'])
            ->unique(fn (QuestionAttempt $attempt): string => $attempt->session_id.':'.$attempt->question_id)
            ->groupBy('session_id');

        return $tasks
            ->groupBy(fn (StudyPlanTask $task) => (int) floor($start->diffInDays($task->date) / 7))
            ->sortKeys()
            ->values()
            ->map(function (Collection $weekTasks, int $index) use ($attemptsBySession): array {
                $days = $weekTasks
                    ->groupBy(fn (StudyPlanTask $task) => $task->date->toDateString())
                    ->map(fn (Collection $dayTasks, string $date) => $this->day($date, $dayTasks, $attemptsBySession))
                    ->values()
                    ->all();

                $doneDays = collect($days)->where('status', 'done')->count();

                return [
                    'index' => $index + 1,
                    'title' => 'Tuần '.($index + 1),
                    'progress' => $doneDays.'/'.count($days).' hoàn thành',
                    'days' => $days,
                ];
            })
            ->all();
    }

    /**
     * Index of the week containing today, so detail can open it by default.
     *
     * @param  array<int, array<string, mixed>>  $weeks
     */
    public function currentWeekIndex(array $weeks): int
    {
        foreach ($weeks as $week) {
            foreach ($week['days'] as $day) {
                if ($day['isToday']) {
                    return $week['index'];
                }
            }
        }

        return $weeks[0]['index'] ?? 1;
    }

    /**
     * Completion per classified system represented by allocated plan questions.
     *
     * @return array<int, array{name: string, completed: int, total: int, percent: int}>
     */
    public function topicProgress(StudyPlan $plan): array
    {
        $allocations = StudyPlanQuestion::query()
            ->whereHas('day', fn ($query) => $query->where('study_plan_id', $plan->getKey()))
            ->with('question.medicalTaxonomyNodes')
            ->get();
        if ($allocations->isEmpty()) {
            return [];
        }

        $sessionIds = $plan->tasks()
            ->get()
            ->map(fn (StudyPlanTask $task): ?string => $task->sessionId())
            ->filter()
            ->unique()
            ->values();
        $answeredQuestionIds = QuestionAttempt::query()
            ->whereIn('session_id', $sessionIds)
            ->whereNotNull('is_correct')
            ->pluck('question_id')
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->flip();
        $topics = [];

        foreach ($allocations as $allocation) {
            $question = $allocation->question;
            if ($question === null) {
                continue;
            }

            $nodes = $question->medicalTaxonomyNodes;
            $systemNodes = $nodes->where('node_type', 'system');
            if ($systemNodes->isNotEmpty()) {
                $nodes = $systemNodes;
            }

            foreach ($nodes->unique('id') as $node) {
                $topics[$node->id] ??= [
                    'name' => $node->name,
                    'sort_order' => (int) $node->sort_order,
                    'question_ids' => [],
                    'completed_ids' => [],
                ];
                $questionId = (string) $question->getKey();
                $topics[$node->id]['question_ids'][$questionId] = true;
                if ($answeredQuestionIds->has($questionId)) {
                    $topics[$node->id]['completed_ids'][$questionId] = true;
                }
            }
        }

        return collect($topics)
            ->sortBy(fn (array $topic): array => [$topic['sort_order'], $topic['name']])
            ->map(function (array $topic): array {
                $total = count($topic['question_ids']);
                $completed = count($topic['completed_ids']);

                return [
                    'name' => $topic['name'],
                    'completed' => $completed,
                    'total' => $total,
                    'percent' => $total > 0 ? (int) round($completed / $total * 100) : 0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, StudyPlanTask>  $tasks
     * @return array<string, mixed>
     */
    private function day(string $date, Collection $tasks, Collection $attemptsBySession): array
    {
        $day = Carbon::parse($date);
        $status = $this->dayStatus($tasks, $day);

        return [
            'date' => $day,
            'label' => $day->translatedFormat('j \t\h\á\n\g n, Y'),
            'isToday' => $day->isToday(),
            'status' => $status,
            'statusLabel' => match ($status) {
                'skipped' => 'Bỏ qua',
                'incomplete' => 'Chưa xong',
                'done' => 'Hoàn thành',
                default => null,
            },
            'statusClass' => match ($status) {
                'skipped' => 'bg-red-50 text-red-600',
                'incomplete' => 'bg-amber-50 text-amber-700',
                'done' => 'bg-[#e6f4ea] text-[#137333]',
                default => '',
            },
            'done' => (int) $tasks->sum('done'),
            'target' => (int) $tasks->sum('target'),
            'outcomes' => $this->outcomes($tasks, $attemptsBySession),
            'tasks' => $tasks->values(),
        ];
    }

    /**
     * @param  Collection<int, StudyPlanTask>  $tasks
     * @param  Collection<string, Collection<int, QuestionAttempt>>  $attemptsBySession
     * @return array{correct: int, correct_with_hints: int, incorrect: int}|null
     */
    private function outcomes(Collection $tasks, Collection $attemptsBySession): ?array
    {
        $attempts = $tasks
            ->map(fn (StudyPlanTask $task): ?string => $task->sessionId())
            ->filter()
            ->flatMap(fn (string $sessionId): Collection => $attemptsBySession->get($sessionId, collect()))
            ->values();

        $answered = $attempts->count();
        if ($answered === 0) {
            return null;
        }

        $correctWithHints = $attempts->filter(
            fn (QuestionAttempt $attempt): bool => $attempt->is_correct && $attempt->used_hint,
        )->count();
        $correct = $attempts->filter(
            fn (QuestionAttempt $attempt): bool => $attempt->is_correct && ! $attempt->used_hint,
        )->count();
        $incorrect = $attempts->filter(
            fn (QuestionAttempt $attempt): bool => ! $attempt->is_correct,
        )->count();

        return [
            'correct' => (int) round($correct / $answered * 100),
            'correct_with_hints' => (int) round($correctWithHints / $answered * 100),
            'incorrect' => (int) round($incorrect / $answered * 100),
        ];
    }

    /**
     * @param  Collection<int, StudyPlanTask>  $tasks
     */
    private function dayStatus(Collection $tasks, Carbon $day): string
    {
        if ($tasks->every(fn (StudyPlanTask $task) => $task->status === TaskStatus::Done)) {
            return 'done';
        }

        if ($tasks->contains(fn (StudyPlanTask $task) => $task->status === TaskStatus::Skipped)) {
            return 'skipped';
        }

        if ($tasks->contains(fn (StudyPlanTask $task) => $task->done > 0)) {
            return 'incomplete';
        }

        return $day->lessThan(Carbon::today()) ? 'skipped' : 'pending';
    }
}
