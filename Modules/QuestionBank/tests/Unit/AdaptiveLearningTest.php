<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Unit;

use Carbon\CarbonImmutable;
use Modules\QuestionBank\Support\AdaptiveLearning;
use Tests\TestCase;

final class AdaptiveLearningTest extends TestCase
{
    public function test_new_question_quota_bands(): void
    {
        $this->assertSame(10, AdaptiveLearning::newQuestionQuota(0, 50, 0, 10)['count']);
        $this->assertSame(3, AdaptiveLearning::newQuestionQuota(0, 50, 20, 10)['count']);
        $this->assertSame(2, AdaptiveLearning::newQuestionQuota(10, 50, 20, 10)['count']);
        $this->assertSame(1, AdaptiveLearning::newQuestionQuota(30, 50, 40, 10)['count']);
        $this->assertSame('high', AdaptiveLearning::newQuestionQuota(30, 50, 40, 10)['band']);
    }

    public function test_weakness_laplace_on_recent_window(): void
    {
        $this->assertEqualsWithDelta(2 / 3, AdaptiveLearning::weakness([false]), 1e-9);
        $this->assertEqualsWithDelta(1 / 3, AdaptiveLearning::weakness([true]), 1e-9);
    }

    public function test_thrash_mild_needs_time_and_sessions(): void
    {
        $now = CarbonImmutable::parse('2026-10-04 12:00:00');
        $blockedUntil = $now->addHours(72);

        $this->assertTrue(AdaptiveLearning::isThrashBlocked($blockedUntil, 3, 0, $now));
        $this->assertTrue(AdaptiveLearning::isThrashBlocked($now->subHour(), 3, 0, $now)); // hết giờ nhưng chưa đủ phiên
        $this->assertFalse(AdaptiveLearning::isThrashBlocked($now->subHour(), 3, 2, $now));
        $this->assertFalse(AdaptiveLearning::isThrashBlocked(null, 1, 0, $now));
    }

    public function test_after_grade_sets_severe_thrash_at_five(): void
    {
        $at = CarbonImmutable::parse('2026-10-04 12:00:00');

        $mild = AdaptiveLearning::afterGrade([false, false], 2, false, $at, $at);
        $this->assertSame(3, $mild['wrong_streak']);
        $this->assertNotNull($mild['thrash_blocked_until']);
        $this->assertTrue($mild['thrash_blocked_until']->equalTo($at->addHours(72)));

        $severe = AdaptiveLearning::afterGrade([false, false, false, false], 4, false, $at, $at);
        $this->assertSame(5, $severe['wrong_streak']);
        $this->assertNotNull($severe['thrash_blocked_until']);
        $this->assertTrue($severe['thrash_blocked_until']->equalTo($at->addDays(7)));
    }
}
