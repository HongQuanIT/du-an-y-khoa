<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionOption;

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
            'key_info' => [],
            'attending_tip' => null,
            'difficulty' => $this->faker->randomElement(Difficulty::cases()),
            'status' => QuestionStatus::Published,
            'is_free' => $this->faker->boolean(30),
            'version' => 0,
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

    /**
     * Attach a full set of answer options (default 4, exactly one correct).
     */
    public function withOptions(int $count = 4): self
    {
        return $this->afterCreating(function (Question $question) use ($count): void {
            $labels = ['A', 'B', 'C', 'D', 'E'];
            $correctIndex = random_int(0, $count - 1);

            for ($i = 0; $i < $count; $i++) {
                QuestionOption::factory()
                    ->when($i === $correctIndex, fn ($factory) => $factory->correct())
                    ->create([
                        'question_id' => $question->getKey(),
                        'label' => $labels[$i],
                        'order' => $i,
                    ]);
            }
        });
    }
}
