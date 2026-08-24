<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\StudyPlan\Enums\TaskStatus;
use Modules\StudyPlan\Enums\TaskType;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Models\StudyPlanTask;

/**
 * @extends Factory<StudyPlanTask>
 */
class StudyPlanTaskFactory extends Factory
{
    protected $model = StudyPlanTask::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'study_plan_id' => StudyPlan::factory(),
            'date' => now()->toDateString(),
            'type' => TaskType::Questions,
            'target' => 20,
            'done' => 0,
            'status' => TaskStatus::Pending,
            'ref' => ['medical_taxonomy_node_ids' => [], 'topic_ids' => [], 'session_id' => null, 'mode' => 'study'],
        ];
    }

    public function done(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Done,
            'done' => $attributes['target'] ?? 20,
        ]);
    }

    public function skipped(): self
    {
        return $this->state(fn () => ['status' => TaskStatus::Skipped]);
    }
}
