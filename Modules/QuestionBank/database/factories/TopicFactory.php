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
     * @var list<string>
     */
    private array $prefixes = [
        'Nội',
        'Ngoại',
        'Tim mạch',
        'Hô hấp',
        'Tiêu hóa',
        'Thận tiết niệu',
        'Thần kinh',
        'Nhi khoa',
        'Sản phụ khoa',
        'Da liễu',
        'Mắt',
        'Tai mũi họng',
        'Cơ xương khớp',
        'Nội tiết',
        'Truyền nhiễm',
        'Huyết học',
        'Ung bướu',
        'Dược lý',
        'Chẩn đoán hình ảnh',
        'Hồi sức',
    ];

    /**
     * @var list<string>
     */
    private array $suffixes = [
        'cơ bản',
        'nâng cao',
        'đại cương',
        'lâm sàng',
        'thực hành',
        'ứng dụng',
        'chuyên sâu',
        'tổng quan',
        'chẩn đoán',
        'điều trị',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = trim($this->faker->unique()->randomElement($this->prefixes).' '.$this->faker->randomElement($this->suffixes));

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
