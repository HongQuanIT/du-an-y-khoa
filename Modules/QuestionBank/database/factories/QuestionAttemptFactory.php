<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;

/**
 * @extends Factory<QuestionAttempt>
 */
class QuestionAttemptFactory extends Factory
{
    protected $model = QuestionAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => QuestionSession::factory(),
            'user_id' => User::factory(),
            'question_id' => Question::factory(),
            'selected_option_ids' => [$this->faker->numberBetween(1, 5)],
            'is_correct' => $this->faker->boolean(),
            'used_hint' => $this->faker->boolean(20),
            'time_spent_seconds' => $this->faker->numberBetween(10, 180),
            'confidence' => $this->faker->randomElement([null, 'low', 'medium', 'high']),
            'flagged' => $this->faker->boolean(15),
            'answered_at' => now(),
        ];
    }

    public function correct(): self
    {
        return $this->state(fn () => ['is_correct' => true]);
    }

    public function incorrect(): self
    {
        return $this->state(fn () => ['is_correct' => false]);
    }
}
