<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Actions;

use App\Support\Concerns\AsAction;
use Modules\QuestionBank\Actions\AnswerQuestionAction;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\StudyPlan\Models\StudyPlanTask;

/**
 * StudyPlan adapter around the QuestionBank-owned answer engine.
 *
 * The wrapper only synchronizes plan-task progress; grading, attempts,
 * question status, session counters and session completion stay in Q-Bank.
 */
final class AnswerPlanQuestionAction
{
    use AsAction;

    public function __construct(
        private readonly AnswerQuestionAction $answerQuestion,
        private readonly CompletePlanTaskAction $completeTask,
    ) {}

    /**
     * @param  array<int, int>  $selectedOptionIds
     */
    public function handle(
        StudyPlanTask $task,
        QuestionSession $session,
        Question $question,
        array $selectedOptionIds,
        int $timeSpentSeconds = 0,
    ): QuestionAttempt {
        $attempt = $this->answerQuestion->handle(
            $session,
            $question,
            $selectedOptionIds,
            $timeSpentSeconds,
        );

        $session->refresh();
        $task->forceFill([
            'done' => min($task->target, $session->answered_count),
        ])->save();

        if ($session->answered_count >= min($task->target, $session->total)) {
            $this->completeTask->handle($task->refresh());
        }

        return $attempt;
    }
}
