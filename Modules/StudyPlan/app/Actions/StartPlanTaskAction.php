<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Actions;

use App\Models\User;
use App\Support\Audit\AuditContext;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Facades\DB;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Services\QuestionSessionSnapshots;
use Modules\StudyPlan\Events\StudyPlanActivity;
use Modules\StudyPlan\Models\StudyPlanDay;
use Modules\StudyPlan\Models\StudyPlanTask;
use Modules\StudyPlan\Services\PlanQuestionSelector;
use Modules\StudyPlan\Services\StudyPlanQuestionPool;
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
        private readonly StudyPlanQuestionPool $questionPool,
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
            $this->replaceDayAllocation($task, $questionIds);

            $session = QuestionSession::create([
                'user_id' => $task->plan->user_id,
                'mode' => SessionMode::Study,
                'status' => SessionStatus::Active,
                'source' => SessionSource::StudyPlan,
                'filters' => [
                    'study_plan_id' => $task->study_plan_id,
                    'study_plan_task_id' => $task->getKey(),
                    'medical_taxonomy_node_ids' => $task->medicalTaxonomyNodeIds(),
                ],
                'question_ids' => $questionIds,
                'total' => count($questionIds),
            ]);
            $this->snapshots->capture($session);

            $task->forceFill([
                'target' => count($questionIds),
                'ref' => array_merge($task->ref ?? [], [
                    'question_ids' => $questionIds,
                    'session_id' => $session->getKey(),
                ]),
            ])->save();

            return $session;
        });

        event(StudyPlanActivity::taskStarted($task, $session->getKey()));

        $actor = $task->plan->user()->first();
        Auditor::record(
            AuditAction::LearningTaskStarted,
            $actor instanceof User ? $actor : null,
            $task,
            metadata: [
                'study_plan_id' => $task->study_plan_id,
                'question_session_id' => $session->getKey(),
                'target' => $task->target,
            ],
            context: new AuditContext(sessionId: (string) $session->getKey()),
        );

        return $session;
    }

    /** @param list<string> $questionIds */
    private function replaceDayAllocation(StudyPlanTask $task, array $questionIds): void
    {
        $dayId = $task->ref['study_plan_day_id'] ?? null;
        if ($dayId === null) {
            return;
        }

        $day = StudyPlanDay::query()
            ->whereKey($dayId)
            ->where('study_plan_id', $task->study_plan_id)
            ->first();
        if ($day === null) {
            return;
        }

        $snapshots = $this->questionPool->candidates($task->plan)
            ->whereIn('question_id', $questionIds)
            ->keyBy('question_id');
        $now = now();
        $rows = collect($questionIds)
            ->map(function (string $questionId, int $order) use ($day, $snapshots, $now): array {
                $snapshot = $snapshots->get($questionId, []);

                return [
                    'study_plan_day_id' => $day->getKey(),
                    'question_id' => $questionId,
                    'order' => $order + 1,
                    'source_status' => $snapshot['source_status'] ?? 'unanswered',
                    'high_yield_score' => $snapshot['high_yield_score'] ?? 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->all();

        $day->questions()->delete();
        if ($rows !== []) {
            DB::table('study_plan_questions')->insert($rows);
        }
        $day->forceFill(['question_count' => count($questionIds)])->save();
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
