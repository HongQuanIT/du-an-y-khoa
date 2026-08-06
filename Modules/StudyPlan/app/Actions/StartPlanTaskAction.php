<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Facades\DB;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Services\QuestionSessionSnapshots;
use Modules\StudyPlan\Events\StudyPlanActivity;
use Modules\StudyPlan\Models\StudyPlanTask;
use Modules\StudyPlan\Services\PlanQuestionSelector;
use RuntimeException;

/**
 * Use case: open the Q-Bank session behind a plan task.
 *
 * Re-entrant: a task that already has a live session resumes it instead of
 * drawing a new batch of questions.
 */
final class StartPlanTaskAction
{
    use AsAction;

    public function __construct(
        private readonly PlanQuestionSelector $selector,
        private readonly QuestionSessionSnapshots $snapshots,
    ) {}

    public function handle(StudyPlanTask $task): QuestionSession
    {
        $existing = $this->existingSession($task);

        if ($existing !== null) {
            return $existing;
        }

        $questionIds = $this->selector->forTask($task, $task->target);

        if ($questionIds === []) {
            throw new RuntimeException('Không còn câu hỏi phù hợp cho nhiệm vụ này.');
        }

        $session = DB::transaction(function () use ($task, $questionIds): QuestionSession {
            if (count($questionIds) < $task->target) {
                $task->forceFill(['target' => count($questionIds)])->save();
            }

            $session = QuestionSession::create([
                'user_id' => $task->plan->user_id,
                'mode' => SessionMode::Study,
                'status' => SessionStatus::Active,
                'source' => SessionSource::StudyPlan,
                'filters' => [
                    'study_plan_id' => $task->study_plan_id,
                    'study_plan_task_id' => $task->getKey(),
                    'topic_ids' => $task->topicIds(),
                ],
                'question_ids' => $questionIds,
                'total' => count($questionIds),
            ]);
            $this->snapshots->capture($session);

            $task->forceFill([
                'ref' => array_merge($task->ref ?? [], ['session_id' => $session->getKey()]),
            ])->save();

            return $session;
        });

        event(StudyPlanActivity::taskStarted($task, $session->getKey()));

        return $session;
    }

    private function existingSession(StudyPlanTask $task): ?QuestionSession
    {
        $sessionId = $task->sessionId();

        if ($sessionId === null) {
            return null;
        }

        return QuestionSession::query()
            ->whereKey($sessionId)
            ->whereIn('status', [SessionStatus::Active, SessionStatus::Paused])
            ->first();
    }
}
