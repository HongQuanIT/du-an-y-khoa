<?php

namespace Modules\Exam\Tests\Unit;

use Modules\Exam\Services\ExamQuotaMatcher;
use PHPUnit\Framework\TestCase;

final class ExamQuotaMatcherTest extends TestCase
{
    public function test_rounding_preserves_total(): void
    {
        $matcher = new ExamQuotaMatcher;
        $this->assertSame(['easy' => 5, 'medium' => 3, 'hard' => 3], $matcher->quotas(11));
        for ($n = 1; $n <= 300; $n++) {
            $this->assertSame($n, array_sum($matcher->quotas($n)));
        }
    }

    public function test_overlapping_topics_can_reassign_questions_without_duplicates(): void
    {
        $result = (new ExamQuotaMatcher)->match([
            1 => ['count' => 1, 'candidates' => ['a' => 'easy', 'b' => 'easy']],
            2 => ['count' => 1, 'candidates' => ['a' => 'easy']],
            3 => ['count' => 2, 'candidates' => ['c' => 'medium', 'd' => 'medium']],
            4 => ['count' => 1, 'candidates' => ['e' => 'hard']],
        ]);
        $this->assertTrue($result['complete']);
        $this->assertSame(2, $result['selected']['a']);
        $this->assertSame(1, $result['selected']['b']);
        $this->assertCount(5, $result['selected']);
    }

    public function test_wrong_difficulty_mix_is_rejected_even_with_enough_questions(): void
    {
        $result = (new ExamQuotaMatcher)->match([1 => ['count' => 3, 'candidates' => ['a' => 'easy', 'b' => 'easy', 'c' => 'easy']]]);
        $this->assertFalse($result['complete']);
    }
}
