<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\StudyPlan\Enums\PlanStatus;
use Modules\StudyPlan\Enums\PlanStrategy;
use Modules\StudyPlan\Models\StudyPlan;

/**
 * @extends Factory<StudyPlan>
 */
class StudyPlanFactory extends Factory
{
    protected $model = StudyPlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Ôn thi Bác sĩ nội trú '.now()->addYear()->year,
            'exam_key' => 'resident',
            'exam_target_date' => now()->addDays(42)->toDateString(),
            'daily_goal_questions' => 20,
            'daily_goal_minutes' => 45,
            'topic_scope' => [],
            'study_days' => [1, 2, 3, 4, 5],
            'strategy' => PlanStrategy::Fixed,
            'status' => PlanStatus::Active,
            'progress_cache' => null,
        ];
    }

    public function adaptive(): self
    {
        return $this->state(fn () => ['strategy' => PlanStrategy::Adaptive]);
    }

    public function paused(): self
    {
        return $this->state(fn () => ['status' => PlanStatus::Paused]);
    }
}
