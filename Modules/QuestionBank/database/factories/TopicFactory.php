<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\QuestionBank\Models\Topic;

/**
 * @extends Factory<Topic>
 */
class TopicFactory extends Factory
{
    protected $model = Topic::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst($this->faker->unique()->words(2, true));

        return [
            'parent_id' => null,
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 999999),
            'type' => $this->faker->randomElement(['specialty', 'system', 'subtopic']),
            'order' => $this->faker->numberBetween(0, 50),
        ];
    }

    public function child(Topic $parent): self
    {
        return $this->state(fn () => [
            'parent_id' => $parent->id,
            'type' => 'system',
        ]);
    }
}
