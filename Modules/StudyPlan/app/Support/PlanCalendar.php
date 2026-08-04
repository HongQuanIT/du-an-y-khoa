<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\StudyPlan\Enums\TaskStatus;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Models\StudyPlanTask;

/**
 * Builds the month grid (leading/trailing padding included) for the schedule
 * page from a single month's worth of tasks.
 */
final class PlanCalendar
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function month(StudyPlan $plan, Carbon $month, Carbon $selected): array
    {
        $start = $month->copy()->startOfMonth()->startOfWeek();
        $end = $month->copy()->endOfMonth()->endOfWeek();

        $byDate = $plan->tasks()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('id')
            ->get()
            ->groupBy(fn (StudyPlanTask $task) => $task->date->toDateString());

        $cells = [];

        for ($date = $start->copy(); $date->lessThanOrEqualTo($end); $date->addDay()) {
            $tasks = $byDate->get($date->toDateString(), collect());

            $cells[] = [
                'date' => $date->copy(),
                'day' => $date->day,
                'inMonth' => $date->isSameMonth($month),
                'isToday' => $date->isToday(),
                'isSelected' => $date->isSameDay($selected),
                'type' => $this->cellType($date, $tasks),
                'events' => $tasks->map(fn (StudyPlanTask $task) => $this->eventLabel($task))->all(),
            ];
        }

        return $cells;
    }

    /**
     * @return Collection<int, StudyPlanTask>
     */
    public function tasksOn(StudyPlan $plan, Carbon $date): Collection
    {
        return $plan->tasks()
            ->whereDate('date', $date->toDateString())
            ->orderBy('type')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, StudyPlanTask>  $tasks
     */
    private function cellType(Carbon $date, Collection $tasks): string
    {
        if ($date->isToday()) {
            return 'today';
        }

        if ($tasks->isEmpty()) {
            return 'plain';
        }

        if ($tasks->every(fn (StudyPlanTask $task) => $task->status === TaskStatus::Done)) {
            return 'completed';
        }

        if ($tasks->contains(fn (StudyPlanTask $task) => $task->isMissed() || $task->status === TaskStatus::Skipped)) {
            return 'missed';
        }

        return 'plain';
    }

    private function eventLabel(StudyPlanTask $task): string
    {
        return $task->target.' '.mb_strtolower($task->type->label());
    }
}
