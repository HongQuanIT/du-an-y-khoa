<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Actions;

use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Analytics\Models\TopicMastery;
use Modules\StudyPlan\Enums\PlanStrategy;
use Modules\StudyPlan\Enums\TaskStatus;
use Modules\StudyPlan\Events\StudyPlanActivity;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Models\StudyPlanTask;

/**
 * Use case: nightly adaptive re-balance (srs/modules/04 §5).
 *
 * Missed days are folded into the upcoming study days instead of piling up,
 * and the topics the learner is weakest at move to the front of the queue.
 * Fixed plans are left alone.
 */
final class ReplanStudyPlanAction
{
    use AsAction;

    /** A day never grows beyond this multiple of the daily goal. */
    private const MAX_DAILY_OVERLOAD = 2.0;

    public function __construct(private readonly RecalculatePlanProgressAction $recalculateProgress) {}

    public function handle(StudyPlan $plan): int
    {
        if ($plan->strategy !== PlanStrategy::Adaptive || ! $plan->isActive()) {
            return 0;
        }

        $missed = $plan->tasks()
            ->where('status', TaskStatus::Pending)
            ->whereDate('date', '<', Carbon::today())
            ->orderBy('date')
            ->get();

        $upcoming = $plan->tasks()
            ->where('status', TaskStatus::Pending)
            ->whereDate('date', '>=', Carbon::today())
            ->orderBy('date')
            ->get();

        if ($missed->isEmpty()) {
            $this->prioritiseWeakTopics($plan, $upcoming);
            $plan->forceFill(['replanned_at' => Carbon::now()])->save();

            return 0;
        }

        $moved = DB::transaction(function () use ($plan, $missed, $upcoming): int {
            $ceiling = (int) round($plan->daily_goal_questions * self::MAX_DAILY_OVERLOAD);
            $slots = $this->capacityByDate($upcoming, $ceiling);
            $moved = 0;

            foreach ($missed as $task) {
                $date = $this->nextDateWithRoom($slots, $task->target);

                if ($date === null) {
                    // Nothing fits before the exam: drop the day rather than
                    // pretending it will still happen.
                    $task->forceFill(['status' => TaskStatus::Skipped])->save();

                    continue;
                }

                $task->forceFill(['date' => $date])->save();
                $slots[$date] -= $task->target;
                $moved++;
            }

            $this->prioritiseWeakTopics($plan, $upcoming);

            return $moved;
        });

        $plan->forceFill(['replanned_at' => Carbon::now()])->save();
        $this->recalculateProgress->handle($plan->refresh());

        event(StudyPlanActivity::replanned($plan, $moved));

        $actor = $plan->user()->first();
        Auditor::record(
            AuditAction::LearningPlanReplanned,
            $actor instanceof User ? $actor : null,
            $plan,
            metadata: ['moved_tasks' => $moved],
        );

        return $moved;
    }

    /**
     * Remaining question budget per upcoming study day.
     *
     * @param  Collection<int, StudyPlanTask>  $upcoming
     * @return array<string, int>
     */
    private function capacityByDate(Collection $upcoming, int $ceiling): array
    {
        $slots = [];

        foreach ($upcoming as $task) {
            $date = $task->date->toDateString();
            $slots[$date] = ($slots[$date] ?? $ceiling) - $task->target;
        }

        return $slots;
    }

    /**
     * @param  array<string, int>  $slots
     */
    private function nextDateWithRoom(array $slots, int $target): ?string
    {
        foreach ($slots as $date => $room) {
            if ($room >= $target) {
                return $date;
            }
        }

        return null;
    }

    /**
     * Point the soonest tasks at the topics with the lowest accuracy.
     *
     * @param  Collection<int, StudyPlanTask>  $upcoming
     */
    private function prioritiseWeakTopics(StudyPlan $plan, Collection $upcoming): void
    {
        $weakTopics = TopicMastery::query()
            ->where('user_id', $plan->user_id)
            ->when(
                $plan->scopeTopicIds() !== [],
                fn ($query) => $query->whereIn('medical_taxonomy_node_id', $plan->scopeMedicalTaxonomyNodeIds()),
            )
            ->where('attempts', '>', 0)
            ->orderBy('correct_rate')
            ->limit(3)
            ->pluck('medical_taxonomy_node_id')
            ->all();

        if ($weakTopics === []) {
            return;
        }

        foreach ($upcoming->take(count($weakTopics))->values() as $index => $task) {
            $task->forceFill([
                'ref' => array_merge($task->ref ?? [], ['medical_taxonomy_node_ids' => [$weakTopics[$index]], 'topic_ids' => [$weakTopics[$index]]]),
            ])->save();
        }
    }
}
