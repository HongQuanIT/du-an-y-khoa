<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Events;

use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Models\StudyPlanTask;

/**
 * Analytics event for the study-plan funnel
 * (srs/00-nen-tang/06-tracking-analytics.md, srs/modules/04 §11).
 *
 * One event class keeps the tracking surface in a single place; the `$name`
 * carries the SRS event key.
 */
final class StudyPlanActivity
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $name,
        public readonly StudyPlan $plan,
        public readonly array $context = [],
    ) {}

    public static function created(StudyPlan $plan): self
    {
        return new self('study_plan_create', $plan, [
            'strategy' => $plan->strategy->value,
            'daily_goal_questions' => $plan->daily_goal_questions,
            'exam_target_date' => $plan->exam_target_date->toDateString(),
        ]);
    }

    public static function viewed(StudyPlan $plan): self
    {
        return new self('study_plan_view', $plan);
    }

    public static function taskStarted(StudyPlanTask $task, ?string $sessionId): self
    {
        return new self('study_plan_task_start', $task->plan, [
            'task_id' => $task->getKey(),
            'type' => $task->type->value,
            'session_id' => $sessionId,
        ]);
    }

    public static function taskCompleted(StudyPlanTask $task): self
    {
        return new self('study_plan_task_complete', $task->plan, [
            'task_id' => $task->getKey(),
            'type' => $task->type->value,
            'done' => $task->done,
            'target' => $task->target,
        ]);
    }

    public static function rescheduled(StudyPlanTask $task, string $from, string $to): self
    {
        return new self('study_plan_reschedule', $task->plan, [
            'task_id' => $task->getKey(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public static function replanned(StudyPlan $plan, int $movedTasks): self
    {
        return new self('study_plan_replan', $plan, [
            'auto' => true,
            'moved_tasks' => $movedTasks,
        ]);
    }

    public static function deleted(StudyPlan $plan): self
    {
        return new self('study_plan_delete', $plan);
    }
}
