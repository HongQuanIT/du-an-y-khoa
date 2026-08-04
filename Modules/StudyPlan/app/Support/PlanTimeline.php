<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\QuestionBank\Models\Topic;
use Modules\StudyPlan\Enums\TaskStatus;
use Modules\StudyPlan\Models\StudyPlan;
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

        return $tasks
            ->groupBy(fn (StudyPlanTask $task) => (int) floor($start->diffInDays($task->date) / 7))
            ->sortKeys()
            ->values()
            ->map(function (Collection $weekTasks, int $index): array {
                $days = $weekTasks
                    ->groupBy(fn (StudyPlanTask $task) => $task->date->toDateString())
                    ->map(fn (Collection $dayTasks, string $date) => $this->day($date, $dayTasks))
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
     * Completion per topic in the plan scope, for the sidebar bars.
     *
     * @return array<int, array{name: string, percent: int}>
     */
    public function topicProgress(StudyPlan $plan): array
    {
        $topicIds = $plan->scopeTopicIds();

        if ($topicIds === []) {
            return [];
        }

        $names = Topic::query()->whereIn('id', $topicIds)->pluck('name', 'id');
        $tasks = $plan->tasks()->get();
        $progress = [];

        foreach ($topicIds as $topicId) {
            $forTopic = $tasks->filter(
                fn (StudyPlanTask $task) => in_array($topicId, $task->topicIds(), true)
            );

            $target = (int) $forTopic->sum('target');
            $done = (int) $forTopic->sum('done');

            $progress[] = [
                'name' => $names[$topicId] ?? 'Chủ đề',
                'percent' => $target > 0 ? (int) min(100, round($done / $target * 100)) : 0,
            ];
        }

        return $progress;
    }

    /**
     * @param  Collection<int, StudyPlanTask>  $tasks
     * @return array<string, mixed>
     */
    private function day(string $date, Collection $tasks): array
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
            'tasks' => $tasks->values(),
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
