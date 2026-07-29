<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stem' => $this->faker->sentence(12).'?',
            'explanation' => $this->faker->paragraph(),
            'difficulty' => $this->faker->randomElement(Difficulty::cases()),
            'status' => QuestionStatus::Published,
            'topic_id' => $this->faker->numberBetween(1, 50),
            'is_free' => $this->faker->boolean(30),
        ];
    }

    public function draft(): self
    {
        return $this->state(fn () => ['status' => QuestionStatus::Draft]);
    }

    public function free(): self
    {
        return $this->state(fn () => ['is_free' => true]);
    }
}
