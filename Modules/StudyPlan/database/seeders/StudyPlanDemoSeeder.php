<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Modules\Analytics\Actions\RecalculateTopicMasteryAction;
use Modules\QuestionBank\Models\MedicalTaxonomy;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
use Modules\StudyPlan\Actions\GenerateFixedTasksAction;
use Modules\StudyPlan\Actions\RecalculatePlanProgressAction;
use Modules\StudyPlan\Enums\PlanStatus;
use Modules\StudyPlan\Enums\PlanStrategy;
use Modules\StudyPlan\Enums\TaskStatus;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Models\StudyPlanTask;

/**
 * One realistic plan for the demo student: two weeks of history behind us and
 * six weeks of scheduled work ahead, so overview/detail/calendar all have
 * something to render.
 *
 * Idempotent: keyed on (user, plan name).
 */
class StudyPlanDemoSeeder extends Seeder
{
    private const PLAN_NAME = 'Ôn thi Bác sĩ nội trú';

    private const HISTORY_WEEKS = 2;

    private const FUTURE_WEEKS = 6;

    public function run(): void
    {
        $student = User::where('email', 'student@medlearn.local')->first();

        // Nothing to attach a demo plan to until AuthDatabaseSeeder has run.
        if ($student === null) {
            return;
        }

        $name = self::PLAN_NAME.' '.Carbon::today()->addWeeks(self::FUTURE_WEEKS)->year;

        if (StudyPlan::where('user_id', $student->id)->where('name', $name)->exists()) {
            return;
        }

        $start = Carbon::today()->subWeeks(self::HISTORY_WEEKS);

        $plan = StudyPlan::create([
            'user_id' => $student->id,
            'name' => $name,
            'exam_key' => 'resident',
            'exam_target_date' => Carbon::today()->addWeeks(self::FUTURE_WEEKS),
            'daily_goal_questions' => 20,
            'daily_goal_minutes' => 45,
            'topic_scope' => $this->scopeTopicIds(),
            'study_days' => [1, 2, 3, 4, 5, 6, 7], // every day, so the demo always has work for today
            'strategy' => PlanStrategy::Fixed,
            'status' => PlanStatus::Active,
            'created_at' => $start,
            'updated_at' => $start,
        ]);

        app(GenerateFixedTasksAction::class)->handle($plan, $start);
        $this->fillHistory($plan);
        app(RecalculatePlanProgressAction::class)->handle($plan->refresh());
        app(RecalculateTopicMasteryAction::class)->handle($student->id);
    }

    /**
     * Cardiology / respiratory / antibiotics — the areas the demo questions
     * cover best.
     *
     * @return array<int, int>
     */
    private function scopeTopicIds(): array
    {
        return MedicalTaxonomyNode::query()
            ->whereIn('slug', ['tim-mach', 'ho-hap', 'khang-sinh'])
            ->pluck('id')
            ->all();
    }

    /**
     * Give the past a believable mix: mostly done, one partially finished day
     * and one skipped day so the timeline shows every state.
     */
    private function fillHistory(StudyPlan $plan): void
    {
        $past = $plan->tasks()
            ->whereDate('date', '<', Carbon::today())
            ->orderBy('date')
            ->get();

        foreach ($past->values() as $index => $task) {
            match ($index % 5) {
                3 => $this->markPartial($task),
                4 => $task->forceFill(['status' => TaskStatus::Skipped])->save(),
                default => $task->forceFill([
                    'status' => TaskStatus::Done,
                    'done' => $task->target,
                ])->save(),
            };
        }
    }

    private function markPartial(StudyPlanTask $task): void
    {
        $task->forceFill(['done' => (int) floor($task->target / 4)])->save();
    }
}
