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

    public function test_custom_difficulty_weights_are_used(): void
    {
        $matcher = new ExamQuotaMatcher;

        $this->assertSame(
            ['easy' => 5, 'medium' => 2, 'hard' => 3],
            $matcher->quotas(10, ['easy' => 50, 'medium' => 20, 'hard' => 30]),
        );
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

    public function test_balanced_match_spreads_all_difficulties_across_topics(): void
    {
        $topics = [];
        for ($topicId = 1; $topicId <= 3; $topicId++) {
            $candidates = [];
            foreach (['easy', 'medium', 'hard'] as $group) {
                for ($number = 1; $number <= 3; $number++) {
                    $candidates[$topicId.'-'.$group.'-'.$number] = $group;
                }
            }
            $topics[$topicId] = ['count' => 3, 'candidates' => $candidates];
        }

        $result = (new ExamQuotaMatcher)->matchBalanced($topics, ['easy' => 34, 'medium' => 33, 'hard' => 33]);

        $this->assertTrue($result['complete']);
        $this->assertCount(9, $result['selected']);
        foreach ($topics as $topicId => $topic) {
            $groups = [];
            foreach ($result['selected'] as $questionId => $selectedTopicId) {
                if ($selectedTopicId === $topicId) {
                    $groups[] = $topic['candidates'][$questionId];
                }
            }
            $counts = array_count_values($groups);
            ksort($counts);
            $this->assertSame(['easy' => 1, 'hard' => 1, 'medium' => 1], $counts);
        }
    }

    public function test_balanced_match_reassigns_shared_questions_without_reusing_them(): void
    {
        $topics = [
            1 => ['count' => 1, 'candidates' => ['shared' => 'easy', 'other' => 'easy']],
            2 => ['count' => 1, 'candidates' => ['shared' => 'easy']],
            3 => ['count' => 1, 'candidates' => ['medium' => 'medium']],
            4 => ['count' => 1, 'candidates' => ['hard' => 'hard']],
        ];

        $result = (new ExamQuotaMatcher)->matchBalanced($topics, ['easy' => 50, 'medium' => 25, 'hard' => 25]);

        $this->assertTrue($result['complete']);
        $this->assertSame(2, $result['selected']['shared']);
        $this->assertSame(1, $result['selected']['other']);
        $this->assertCount(4, $result['selected']);
    }
}
