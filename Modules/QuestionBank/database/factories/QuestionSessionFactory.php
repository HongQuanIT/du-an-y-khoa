<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\QuestionSession;

/**
 * @extends Factory<QuestionSession>
 */
class QuestionSessionFactory extends Factory
{
    protected $model = QuestionSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = $this->faker->numberBetween(5, 20);
        $answered = $this->faker->numberBetween(0, $total);
        $correct = $this->faker->numberBetween(0, $answered);

        return [
            'user_id' => User::factory(),
            'mode' => SessionMode::Study,
            'status' => SessionStatus::Completed,
            'filters' => ['difficulty' => null],
            'question_ids' => [],
            'total' => $total,
            'answered_count' => $answered,
            'correct_count' => $correct,
            'time_limit_seconds' => null,
            'paused_state' => null,
        ];
    }

    public function exam(int $timeLimitSeconds = 3600): self
    {
        return $this->state(fn () => [
            'mode' => SessionMode::Exam,
            'time_limit_seconds' => $timeLimitSeconds,
        ]);
    }

    public function paused(): self
    {
        return $this->state(fn () => [
            'status' => SessionStatus::Paused,
            'paused_state' => ['current_index' => $this->faker->numberBetween(1, 5)],
        ]);
    }
}
