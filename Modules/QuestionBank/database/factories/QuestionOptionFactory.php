<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionOption;

/**
 * @extends Factory<QuestionOption>
 */
class QuestionOptionFactory extends Factory
{
    protected $model = QuestionOption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'label' => $this->faker->randomElement(['A', 'B', 'C', 'D', 'E']),
            'content' => $this->faker->sentence(6),
            'is_correct' => false,
            'explanation' => $this->faker->boolean(40) ? $this->faker->sentence() : null,
            'order' => $this->faker->numberBetween(0, 4),
        ];
    }

    public function correct(): self
    {
        return $this->state(fn () => ['is_correct' => true]);
    }
}
