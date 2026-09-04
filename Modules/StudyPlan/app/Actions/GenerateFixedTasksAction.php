<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\StudyPlan\Enums\TaskStatus;
use Modules\StudyPlan\Enums\TaskType;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Services\StudyPlanQuestionPool;

/**
 * Use case: lay out the day-by-day task grid for a plan.
 *
 * Fixed strategy: eligible questions are reserved once, split by the daily
 * maximum, and stop when the selected scope is exhausted. Every study week
 * may end with a review task for previously missed questions.
 *
 * Regeneration (`$from`) only replaces pending future tasks — finished days and
 * their sessions stay untouched.
 */
final class GenerateFixedTasksAction
{
    use AsAction;

    public function __construct(private readonly StudyPlanQuestionPool $questionPool) {}

    /** Weekly review target as a share of the daily question goal. */
    private const REVIEW_RATIO = 0.5;

    public function handle(StudyPlan $plan, ?Carbon $from = null): int
    {
        $from = ($from ?? Carbon::today())->copy()->startOfDay();
        $until = $plan->exam_target_date->copy()->startOfDay();

        if ($until->lessThan($from)) {
            return 0;
        }

        return DB::transaction(function () use ($plan, $from, $until): int {
            $plan->tasks()
                ->where('status', TaskStatus::Pending)
                ->whereDate('date', '>=', $from)
                ->delete();

            $topics = $plan->scopeTopicIds();
            $weekdays = $plan->studyWeekdays();
            $allAvailableQuestions = array_values(array_unique($this->questionPool->questionIds($plan)));
            if (empty($allAvailableQuestions)) {
                return 0;
            }

            $alreadyScheduled = $plan->tasks()
                ->where('status', '!=', TaskStatus::Pending->value)
                ->get()
                ->flatMap(fn ($task) => (array) ($task->ref['question_ids'] ?? []))
                ->map(fn ($id): string => (string) $id)
                ->all();

            // First pass: questions that haven't been scheduled yet
            $remainingPool = array_values(array_diff($allAvailableQuestions, $alreadyScheduled));
            $dailyGoal = max(1, $plan->daily_goal_questions);
            $rows = [];
            $now = Carbon::now();

            for ($date = $from->copy(); $date->lessThanOrEqualTo($until); $date->addDay()) {
                if (! in_array($date->dayOfWeekIso, $weekdays, true)) {
                    continue;
                }

                $questionsForDay = [];
                while (count($questionsForDay) < $dailyGoal) {
                    if (empty($remainingPool)) {
                        // Refill from all available questions and shuffle for the next cycle
                        $refill = $allAvailableQuestions;
                        shuffle($refill);
                        $remainingPool = array_values($refill);
                    }

                    $needed = $dailyGoal - count($questionsForDay);
                    $chunk = array_splice($remainingPool, 0, $needed);
                    if (empty($chunk)) {
                        break;
                    }
                    $questionsForDay = array_merge($questionsForDay, $chunk);
                }

                if (empty($questionsForDay)) {
                    break;
                }

                $rows[] = [
                    'study_plan_id' => $plan->getKey(),
                    'date' => $date->toDateString(),
                    'type' => TaskType::Questions->value,
                    'target' => count($questionsForDay),
                    'done' => 0,
                    'status' => TaskStatus::Pending->value,
                    'ref' => json_encode([
                        'medical_taxonomy_node_ids' => $topics,
                        'topic_ids' => $topics,
                        'question_ids' => $questionsForDay,
                        'session_id' => null,
                        'mode' => 'study',
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($this->closesTheWeek($date, $weekdays)) {
                    $rows[] = [
                        'study_plan_id' => $plan->getKey(),
                        'date' => $date->toDateString(),
                        'type' => TaskType::Review->value,
                        'target' => max(5, (int) round($plan->daily_goal_questions * self::REVIEW_RATIO)),
                        'done' => 0,
                        'status' => TaskStatus::Pending->value,
                        'ref' => json_encode([
                            'medical_taxonomy_node_ids' => $topics,
                            'topic_ids' => $topics,
                            'session_id' => null,
                            'mode' => 'study',
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('study_plan_tasks')->insert($chunk);
            }

            return count($rows);
        });
    }

    /** @param  array<int, int>  $weekdays */
    private function closesTheWeek(Carbon $date, array $weekdays): bool
    {
        return $date->dayOfWeekIso === max($weekdays);
    }
}
