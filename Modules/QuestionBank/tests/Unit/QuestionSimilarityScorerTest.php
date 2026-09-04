<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\DuplicateSeverity;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Services\QuestionContentFingerprint;
use Modules\QuestionBank\Services\QuestionSimilarityScorer;
use Tests\TestCase;

final class QuestionSimilarityScorerTest extends TestCase
{
    use RefreshDatabase;

    public function test_fingerprint_stable_across_html_and_option_order(): void
    {
        $service = app(QuestionContentFingerprint::class);

        $a = $this->makeQuestion(
            '<p>Bệnh nhân <strong>55</strong> tuổi đau ngực?</p>',
            [
                ['content' => 'ACS', 'is_correct' => true],
                ['content' => 'GERD', 'is_correct' => false],
            ],
        );
        $b = $this->makeQuestion(
            'Bệnh nhân 55 tuổi đau ngực?',
            [
                ['content' => 'GERD', 'is_correct' => false],
                ['content' => 'ACS', 'is_correct' => true],
            ],
        );

        $this->assertSame($service->fingerprint($a), $service->fingerprint($b));
    }

    public function test_scorer_marks_exact_and_near_and_ignores_low_overlap(): void
    {
        $scorer = app(QuestionSimilarityScorer::class);
        $fp = app(QuestionContentFingerprint::class);

        $base = $this->makeQuestion(
            'Bệnh nhân 55 tuổi đau ngực kiểu đè nặng. Chẩn đoán nào phù hợp nhất?',
            [
                ['content' => 'ACS', 'is_correct' => true],
                ['content' => 'GERD', 'is_correct' => false],
                ['content' => 'Anxiety', 'is_correct' => false],
            ],
        );
        $exact = $this->makeQuestion(
            '<p>Bệnh nhân 55 tuổi đau ngực kiểu đè nặng. Chẩn đoán nào phù hợp nhất?</p>',
            [
                ['content' => 'Anxiety', 'is_correct' => false],
                ['content' => 'ACS', 'is_correct' => true],
                ['content' => 'GERD', 'is_correct' => false],
            ],
        );
        $near = $this->makeQuestion(
            'Bệnh nhân 55 tuổi đau ngực kiểu đè nặng kéo dài. Chẩn đoán nào phù hợp nhất?',
            [
                ['content' => 'ACS', 'is_correct' => true],
                ['content' => 'GERD', 'is_correct' => false],
                ['content' => 'Anxiety', 'is_correct' => false],
            ],
        );
        $far = $this->makeQuestion(
            'Trẻ 2 tuổi sốt cao co giật. Xử trí ban đầu là gì?',
            [
                ['content' => 'Diazepam', 'is_correct' => true],
                ['content' => 'Amoxicillin', 'is_correct' => false],
            ],
        );

        foreach ([$base, $exact, $near, $far] as $question) {
            $fp->persist($question);
        }

        $exactResult = $scorer->score($base->fresh('options'), $exact->fresh('options'));
        $this->assertTrue($exactResult['exact']);
        $this->assertSame(DuplicateSeverity::Exact, $exactResult['severity']);
        $this->assertSame(100.0, $exactResult['percent']);

        $nearResult = $scorer->score($base->fresh('options'), $near->fresh('options'));
        $this->assertNotNull($nearResult['severity']);
        $this->assertGreaterThanOrEqual(60.0, $nearResult['percent']);

        $farResult = $scorer->score($base->fresh('options'), $far->fresh('options'));
        $this->assertNull($farResult['severity']);
        $this->assertLessThan(30.0, $farResult['percent']);
    }

    public function test_duplicate_severity_from_percent(): void
    {
        $this->assertSame(DuplicateSeverity::Exact, DuplicateSeverity::fromPercent(100));
        $this->assertSame(DuplicateSeverity::VeryHigh, DuplicateSeverity::fromPercent(90));
        $this->assertSame(DuplicateSeverity::High, DuplicateSeverity::fromPercent(75));
        $this->assertSame(DuplicateSeverity::Medium, DuplicateSeverity::fromPercent(60));
        $this->assertSame(DuplicateSeverity::Low, DuplicateSeverity::fromPercent(30));
        $this->assertNull(DuplicateSeverity::fromPercent(29.9));
    }

    /**
     * @param  list<array{content: string, is_correct: bool}>  $options
     */
    private function makeQuestion(string $stem, array $options): Question
    {
        $question = Question::factory()->create([
            'stem' => $stem,
            'difficulty' => Difficulty::Medium,
            'status' => QuestionStatus::Draft,
            'is_free' => true,
            'version' => 0,
        ]);

        foreach ($options as $i => $row) {
            $question->options()->create([
                'label' => chr(65 + $i),
                'content' => $row['content'],
                'is_correct' => $row['is_correct'],
                'order' => $i + 1,
            ]);
        }

        return $question->fresh('options');
    }
}
