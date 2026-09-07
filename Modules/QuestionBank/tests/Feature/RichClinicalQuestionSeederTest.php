<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\QuestionBank\Database\Seeders\RichClinicalQuestionSeeder;
use Modules\QuestionBank\Models\Question;
use Tests\TestCase;

final class RichClinicalQuestionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_two_hundred_complete_classified_questions_idempotently(): void
    {
        $this->seed(RichClinicalQuestionSeeder::class);
        $this->seed(RichClinicalQuestionSeeder::class);

        $questions = Question::query()
            ->where('code', 'like', 'RICH-CLIN-%')
            ->withCount(['options', 'hints', 'medicalTaxonomyNodes', 'scopes'])
            ->get();

        $this->assertCount(200, $questions);
        $this->assertTrue($questions->every(fn (Question $question): bool => $question->options_count === 4));
        $this->assertTrue($questions->every(fn (Question $question): bool => $question->hints_count >= 1));
        $this->assertTrue($questions->every(fn (Question $question): bool => $question->medical_taxonomy_nodes_count === 2));
        $this->assertTrue($questions->every(fn (Question $question): bool => $question->scopes_count >= 6));
        $this->assertTrue($questions->every(fn (Question $question): bool => filled($question->explanation)));
        $this->assertTrue($questions->every(fn (Question $question): bool => filled($question->attending_tip)));
        $this->assertSame(200, $questions->filter(
            fn (Question $question): bool => $question->options()->where('is_correct', true)->count() === 1,
        )->count());
    }
}
