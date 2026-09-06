<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Services\QuestionGrader;
use Tests\TestCase;

final class OptionsForSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_options_for_session_is_stable_for_same_seed_and_remaps_display_labels(): void
    {
        $question = Question::factory()->create([
            'status' => QuestionStatus::Published,
            'difficulty' => Difficulty::Medium,
        ]);
        $a = $question->options()->create([
            'label' => 'A',
            'content' => 'Author first',
            'is_correct' => true,
            'order' => 1,
        ]);
        $b = $question->options()->create([
            'label' => 'B',
            'content' => 'Author second',
            'is_correct' => false,
            'order' => 2,
        ]);
        $c = $question->options()->create([
            'label' => 'C',
            'content' => 'Author third',
            'is_correct' => false,
            'order' => 3,
        ]);

        $question = $question->fresh(['options']);
        $sessionKey = 'session-stable-seed';

        $first = $question->optionsForSession($sessionKey);
        $second = $question->fresh(['options'])->optionsForSession($sessionKey);

        $this->assertSame(
            $first->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            $second->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        );
        $this->assertSame(['A', 'B', 'C'], $first->pluck('label')->all());

        // Display letter is positional after shuffle — not tied to author label.
        $byId = $first->keyBy(fn ($opt): int => (int) $opt->getKey());
        $this->assertContains($byId->get((int) $a->id)->label, ['A', 'B', 'C']);

        $otherSession = $question->fresh(['options'])->optionsForSession('other-session-key');
        // Different sessions may permute differently; at minimum identity set is unchanged.
        $this->assertEqualsCanonicalizing(
            [(int) $a->id, (int) $b->id, (int) $c->id],
            $otherSession->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        );
    }

    public function test_grading_uses_option_ids_independent_of_display_order(): void
    {
        $question = Question::factory()->create(['status' => QuestionStatus::Published]);
        $correct = $question->options()->create([
            'label' => 'A',
            'content' => 'Correct content',
            'is_correct' => true,
            'order' => 1,
        ]);
        $question->options()->create([
            'label' => 'B',
            'content' => 'Wrong content',
            'is_correct' => false,
            'order' => 2,
        ]);

        $shuffled = $question->fresh(['options'])->optionsForSession('grade-session');
        $question->setRelation('options', $shuffled);

        $grader = app(QuestionGrader::class);
        $this->assertTrue($grader->isCorrect($question, [(int) $correct->id]));
        $this->assertFalse($grader->isCorrect($question, [
            (int) $shuffled->firstWhere('is_correct', false)->getKey(),
        ]));
    }
}
