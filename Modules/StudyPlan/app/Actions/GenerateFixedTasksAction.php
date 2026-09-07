<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\StudyPlan\Enums\TaskStatus;
use Modules\StudyPlan\Enums\TaskType;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Models\StudyPlanDay;
use Modules\StudyPlan\Services\StudyPlanQuestionPool;

/**
 * Freeze the filtered question pool into evenly distributed study days.
 */
final class GenerateFixedTasksAction
{
    use AsAction;

    public function __construct(private readonly StudyPlanQuestionPool $questionPool) {}

    public function handle(StudyPlan $plan, ?Carbon $from = null): int
    {
        $from = ($from ?? Carbon::today())->copy()->startOfDay();
        $dates = $this->studyDates($plan, $from);
        $candidates = $this->questionPool->candidates($plan);
        $totalPool = $candidates->count();
        $capacity = $dates->count() * max(1, (int) $plan->daily_goal_questions);

        $previousQuestionIds = $plan->days()
            ->whereDate('date', '<', $from)
            ->with('questions:id,study_plan_day_id,question_id')
            ->get()
            ->flatMap(fn (StudyPlanDay $day) => $day->questions->pluck('question_id'))
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
        $available = $candidates
            ->reject(fn (array $candidate): bool => in_array(
                $candidate['question_id'],
                $previousQuestionIds,
                true,
            ))
            ->values();
        $selected = $this->selectQuestions($available, min($capacity, $available->count()));
        $selectedCount = min($totalPool, count($previousQuestionIds) + $selected->count());
        $coverage = $totalPool === 0
            ? 0.0
            : round(min(100, ($capacity / $totalPool) * 100), 2);

        return DB::transaction(function () use (
            $plan,
            $from,
            $dates,
            $selected,
            $totalPool,
            $selectedCount,
            $coverage,
        ): int {
            $plan->tasks()
                ->where('status', TaskStatus::Pending)
                ->whereDate('date', '>=', $from)
                ->delete();
            $plan->days()->whereDate('date', '>=', $from)->delete();

            $plan->forceFill([
                'total_question_pool' => $totalPool,
                'selected_question_count' => $selectedCount,
                'coverage_percent' => $coverage,
                'daily_goal_minutes' => (int) round($plan->hours_per_day * 60),
            ])->save();

            if ($dates->isEmpty()) {
                return 0;
            }

            $counts = $this->dailyCounts($selected->count(), $dates->count());
            $cursor = 0;
            $taskRows = [];
            $now = Carbon::now();

            foreach ($dates->values() as $index => $date) {
                $questionCount = $counts[$index];
                $questions = $selected->slice($cursor, $questionCount)->values();
                $cursor += $questionCount;

                $day = StudyPlanDay::create([
                    'study_plan_id' => $plan->getKey(),
                    'date' => $date->toDateString(),
                    'question_count' => $questionCount,
                    'status' => TaskStatus::Pending->value,
                ]);

                if ($questions->isNotEmpty()) {
                    DB::table('study_plan_questions')->insert(
                        $questions->values()->map(
                            fn (array $candidate, int $order): array => [
                                'study_plan_day_id' => $day->getKey(),
                                'question_id' => $candidate['question_id'],
                                'order' => $order + 1,
                                'source_status' => $candidate['source_status'],
                                'high_yield_score' => $candidate['high_yield_score'],
                                'created_at' => $now,
                                'updated_at' => $now,
                            ],
                        )->all(),
                    );

                    $taskRows[] = [
                        'study_plan_id' => $plan->getKey(),
                        'date' => $date->toDateString(),
                        'type' => TaskType::Questions->value,
                        'target' => $questionCount,
                        'done' => 0,
                        'status' => TaskStatus::Pending->value,
                        'ref' => json_encode([
                            'study_plan_day_id' => $day->getKey(),
                            'medical_taxonomy_node_ids' => $plan->scopeTopicIds(),
                            'topic_ids' => $plan->scopeTopicIds(),
                            'question_ids' => $questions->pluck('question_id')->all(),
                            'session_id' => null,
                            'mode' => 'study',
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if ($taskRows !== []) {
                DB::table('study_plan_tasks')->insert($taskRows);
            }

            return count($taskRows);
        });
    }

    /** @return Collection<int, Carbon> */
    private function studyDates(StudyPlan $plan, Carbon $from): Collection
    {
        $until = $plan->exam_target_date->copy()->startOfDay();
        if ($until->lessThan($from)) {
            return collect();
        }

        $weekdays = $plan->studyWeekdays();
        $dates = collect();

        for ($date = $from->copy(); $date->lessThanOrEqualTo($until); $date->addDay()) {
            if (in_array($date->dayOfWeekIso, $weekdays, true)) {
                $dates->push($date->copy());
            }
        }

        return $dates;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $candidates
     * @return Collection<int, array<string, mixed>>
     */
    private function selectQuestions(Collection $candidates, int $limit): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        $rows = $candidates
            ->map(fn (array $candidate): array => [
                ...$candidate,
                'random_tie' => random_int(1, PHP_INT_MAX),
            ])
            ->all();

        if ($limit < count($rows)) {
            usort($rows, static function (array $left, array $right): int {
                return ($right['high_yield_score'] <=> $left['high_yield_score'])
                    ?: ($right['status_priority'] <=> $left['status_priority'])
                    ?: ($right['random_tie'] <=> $left['random_tie']);
            });
            $rows = array_slice($rows, 0, $limit);
        }

        shuffle($rows);

        return collect($rows)->map(function (array $row): array {
            unset($row['random_tie']);

            return $row;
        });
    }

    /** @return list<int> */
    private function dailyCounts(int $questions, int $days): array
    {
        if ($days <= 0) {
            return [];
        }

        $base = intdiv($questions, $days);
        $remainder = $questions % $days;
        $counts = array_fill(0, $days, $base);

        // When there are fewer questions than study dates, start the learner
        // immediately instead of creating empty days at the beginning.
        if ($base === 0) {
            for ($index = 0; $index < $remainder; $index++) {
                $counts[$index] = 1;
            }

            return $counts;
        }

        for ($index = $days - $remainder; $index < $days; $index++) {
            if ($index >= 0) {
                $counts[$index]++;
            }
        }

        return $counts;
    }
}
