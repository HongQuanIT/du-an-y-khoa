<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Actions;

use App\Models\User;
use App\Support\Audit\AuditContext;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Facades\DB;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\StudyPlan\Enums\TaskStatus;
use Modules\StudyPlan\Events\StudyPlanActivity;
use Modules\StudyPlan\Models\StudyPlanTask;

/**
 * Use case: mark a plan task finished and close its session.
 *
 * Only succeeds when the learner has actually reached the target (`done >=
 * target`). There is no manual "tick done" shortcut — finishing the questions
 * in the session is what completes the task.
 *
 * Idempotent (srs/modules/04 §10): completing an already-done task is a no-op.
 */
final class CompletePlanTaskAction
{
    use AsAction;

    public function __construct(private readonly RecalculatePlanProgressAction $recalculateProgress) {}

    public function handle(StudyPlanTask $task): StudyPlanTask
    {
        if ($task->isDone()) {
            return $task;
        }

        if ($task->done < $task->target) {
            return $task;
        }

        DB::transaction(function () use ($task): void {
            $task->forceFill([
                'status' => TaskStatus::Done,
                'done' => $task->target,
            ])->save();

            $this->closeSession($task);
        });

        $this->recalculateProgress->handle($task->plan);

        event(StudyPlanActivity::taskCompleted($task));

        $actor = $task->plan->user()->first();
        Auditor::record(
            AuditAction::LearningTaskCompleted,
            $actor instanceof User ? $actor : null,
            $task,
            ['status' => TaskStatus::Pending->value],
            ['status' => TaskStatus::Done->value, 'done' => $task->done],
            metadata: ['study_plan_id' => $task->study_plan_id, 'question_session_id' => $task->sessionId()],
            context: new AuditContext(sessionId: $task->sessionId()),
        );

        return $task;
    }

    private function closeSession(StudyPlanTask $task): void
    {
        $sessionId = $task->sessionId();

        if ($sessionId === null) {
            return;
        }

        QuestionSession::query()
            ->whereKey($sessionId)
            ->whereIn('status', [SessionStatus::Active, SessionStatus::Paused])
            ->update(['status' => SessionStatus::Completed->value]);
    }
}
