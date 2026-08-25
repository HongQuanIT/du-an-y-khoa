<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Actions;

use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\StudyPlan\Data\StudyPlanData;
use Modules\StudyPlan\Enums\PlanStrategy;
use Modules\StudyPlan\Models\StudyPlan;

/**
 * Use case: edit a plan and rebuild the days that have not happened yet.
 *
 * Changing the exam date, goal or scope re-balances from tomorrow onwards so
 * today's work in progress is never pulled out from under the learner.
 */
final class UpdateStudyPlanAction
{
    use AsAction;

    public function __construct(
        private readonly GenerateFixedTasksAction $generateTasks,
        private readonly RecalculatePlanProgressAction $recalculateProgress,
    ) {}

    public function handle(StudyPlan $plan, StudyPlanData $data): StudyPlan
    {
        $before = $this->snapshot($plan);
        DB::transaction(function () use ($plan, $data): void {
            $plan->fill([
                'name' => $data->name,
                'exam_key' => $data->examKey,
                'exam_target_date' => $data->examTargetDate,
                'daily_goal_questions' => $data->dailyGoalQuestions,
                'daily_goal_minutes' => $data->dailyGoalMinutes(),
                'topic_scope' => $data->topicScopePayload(),
                'study_days' => $data->studyDays,
                'strategy' => PlanStrategy::from($data->strategy),
            ])->save();

            $this->generateTasks->handle($plan->refresh(), Carbon::tomorrow());
        });

        $plan = $this->recalculateProgress->handle($plan->refresh());
        $actor = $plan->user()->first();
        Auditor::record(
            AuditAction::LearningPlanUpdated,
            $actor instanceof User ? $actor : null,
            $plan,
            $before,
            $this->snapshot($plan),
        );

        return $plan;
    }

    /** @return array<string, mixed> */
    private function snapshot(StudyPlan $plan): array
    {
        return [
            'status' => $plan->status->value,
            'strategy' => $plan->strategy->value,
            'exam_target_date' => $plan->exam_target_date->toDateString(),
            'daily_goal_questions' => $plan->daily_goal_questions,
            'daily_goal_minutes' => $plan->daily_goal_minutes,
            'study_days' => $plan->study_days,
        ];
    }
}
