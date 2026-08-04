<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\StudyPlan\Enums\TaskStatus;
use Modules\StudyPlan\Enums\TaskType;
use Modules\StudyPlan\Models\StudyPlan;

/**
 * Use case: lay out the day-by-day task grid for a plan.
 *
 * Fixed strategy: the daily goal is constant, topics rotate through the scope
 * so each day focuses on one area, and every study week ends with a review
 * task for previously missed questions.
 *
 * Regeneration (`$from`) only replaces pending future tasks — finished days and
 * their sessions stay untouched.
 */
final class GenerateFixedTasksAction
{
    use AsAction;

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
            $rows = [];
            $index = 0;
            $now = Carbon::now();

            for ($date = $from->copy(); $date->lessThanOrEqualTo($until); $date->addDay()) {
                if (! in_array($date->dayOfWeekIso, $weekdays, true)) {
                    continue;
                }

                $rows[] = [
                    'study_plan_id' => $plan->getKey(),
                    'date' => $date->toDateString(),
                    'type' => TaskType::Questions->value,
                    'target' => $plan->daily_goal_questions,
                    'done' => 0,
                    'status' => TaskStatus::Pending->value,
                    'ref' => json_encode([
                        'topic_ids' => $this->topicsForDay($topics, $index),
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
                            'topic_ids' => $topics,
                            'session_id' => null,
                            'mode' => 'study',
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                $index++;
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('study_plan_tasks')->insert($chunk);
            }

            return count($rows);
        });
    }

    /**
     * One topic per day, rotating; an empty scope means "any topic".
     *
     * @param  array<int, int>  $topics
     * @return array<int, int>
     */
    private function topicsForDay(array $topics, int $index): array
    {
        if ($topics === []) {
            return [];
        }

        return [$topics[$index % count($topics)]];
    }

    /** @param  array<int, int>  $weekdays */
    private function closesTheWeek(Carbon $date, array $weekdays): bool
    {
        return $date->dayOfWeekIso === max($weekdays);
    }
}
