<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\StudyPlan\Data\StudyPlanData;
use Modules\StudyPlan\Enums\PlanStatus;
use Modules\StudyPlan\Enums\PlanStrategy;
use Modules\StudyPlan\Events\StudyPlanActivity;
use Modules\StudyPlan\Models\StudyPlan;

/**
 * Use case: turn wizard input into a plan plus its generated task grid.
 *
 * A learner studies one plan at a time (srs/modules/04 §9), so any previously
 * active plan is paused rather than deleted — its history stays readable.
 */
final class CreateStudyPlanAction
{
    use AsAction;

    public function __construct(
        private readonly GenerateFixedTasksAction $generateTasks,
        private readonly RecalculatePlanProgressAction $recalculateProgress,
    ) {}

    public function handle(User $user, StudyPlanData $data): StudyPlan
    {
        $plan = DB::transaction(function () use ($user, $data): StudyPlan {
            StudyPlan::query()
                ->where('user_id', $user->getKey())
                ->where('status', PlanStatus::Active)
                ->update(['status' => PlanStatus::Paused->value]);

            $plan = StudyPlan::create([
                'user_id' => $user->getKey(),
                'name' => $data->name,
                'exam_key' => $data->examKey,
                'exam_target_date' => $data->examTargetDate,
                'daily_goal_questions' => $data->dailyGoalQuestions,
                'daily_goal_minutes' => $data->dailyGoalMinutes(),
                'topic_scope' => $data->topicScopePayload(),
                'study_days' => $data->studyDays,
                'strategy' => PlanStrategy::from($data->strategy),
                'status' => PlanStatus::Active,
            ]);

            $this->generateTasks->handle($plan, Carbon::today());

            return $plan;
        });

        $this->recalculateProgress->handle($plan);

        event(StudyPlanActivity::created($plan));

        return $plan->refresh();
    }
}
