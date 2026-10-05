<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\Profession;
use Modules\QuestionBank\Database\Seeders\PublishedQuestionAudienceSeeder;
use Modules\QuestionBank\Models\Question;
use Tests\TestCase;

final class PublishedQuestionAudienceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_assigns_all_active_professions_to_published_questions_only(): void
    {
        $activeOne = Profession::query()->create([
            'code' => 'nurse',
            'name' => 'Điều dưỡng',
            'is_active' => true,
        ]);
        $activeTwo = Profession::query()->create([
            'code' => 'doctor',
            'name' => 'Bác sĩ',
            'is_active' => true,
        ]);
        $inactive = Profession::query()->create([
            'code' => 'inactive',
            'name' => 'Ngừng dùng',
            'is_active' => false,
        ]);
        $published = Question::factory()->create();
        $draft = Question::factory()->draft()->create();

        $this->seed(PublishedQuestionAudienceSeeder::class);

        $this->assertEqualsCanonicalizing(
            [$activeOne->id, $activeTwo->id],
            $published->fresh()->professions->pluck('id')->all(),
        );
        $this->assertNotContains($inactive->id, $published->fresh()->professions->pluck('id')->all());
        $this->assertCount(0, $draft->fresh()->professions);
    }
}
