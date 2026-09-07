<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Services;

use Modules\StudyPlan\Enums\TaskType;
use Modules\StudyPlan\Models\StudyPlanTask;

/**
 * Picks the questions that make up a plan task.
 *
 * Questions are drawn when the learner starts the task so the newest answer
 * history can exclude not-due mastered questions and prioritise current
 * mistakes, high-yield content and due reviews.
 */
final class PlanQuestionSelector
{
    public function __construct(private readonly StudyPlanQuestionPool $questionPool) {}

    /**
     * @return array<int, string> question ids, at most `$limit`
     */
    public function forTask(StudyPlanTask $task, int $limit): array
    {
        if ($task->type === TaskType::Review) {
            $incorrect = $this->questionPool->incorrectQuestionIds($task->plan, $limit);

            return $incorrect !== []
                ? $incorrect
                : $this->questionPool->nextQuestionIds($task->plan, $limit);
        }

        return $this->questionPool->nextQuestionIds($task->plan, $limit);
    }
}
