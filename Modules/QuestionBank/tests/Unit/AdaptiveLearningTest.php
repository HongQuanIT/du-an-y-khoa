<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Unit;

use Carbon\CarbonImmutable;
use Modules\QuestionBank\Support\AdaptiveLearning;
use Tests\TestCase;

final class AdaptiveLearningTest extends TestCase
{
    public function test_is_valid_graded_response_requires_five_seconds(): void
    {
        $this->assertFalse(AdaptiveLearning::isValidGradedResponse(0));
        $this->assertFalse(AdaptiveLearning::isValidGradedResponse(4));
        $this->assertTrue(AdaptiveLearning::isValidGradedResponse(5));
    }

    public function test_has_answered_attempt_ignores_omit_and_too_fast_still_counts(): void
    {
        $this->assertFalse(AdaptiveLearning::hasAnsweredAttempt('correct', null));
        $this->assertFalse(AdaptiveLearning::hasAnsweredAttempt('omitted', now()));
        $this->assertFalse(AdaptiveLearning::hasAnsweredAttempt('unseen', now()));
        $this->assertTrue(AdaptiveLearning::hasAnsweredAttempt('correct', now()));
        $this->assertTrue(AdaptiveLearning::hasAnsweredAttempt('incorrect', now()));
    }

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

    public function test_serve_ready_at_is_next_study_day_or_min_eight_hours(): void
    {
        $tz = \Modules\QuestionBank\Support\MemoryStability::timezone();
        $format = static fn (CarbonImmutable $at): string => $at->timezone($tz)->format('Y-m-d H:i:s');

        $morning = CarbonImmutable::parse('2026-10-07 08:00:00', $tz);
        $this->assertSame('2026-10-08 04:00:00', $format(AdaptiveLearning::serveReadyAt($morning)));

        $late = CarbonImmutable::parse('2026-10-07 22:00:00', $tz);
        $this->assertSame('2026-10-08 06:00:00', $format(AdaptiveLearning::serveReadyAt($late)));

        $night = CarbonImmutable::parse('2026-10-07 23:30:00', $tz);
        $this->assertSame('2026-10-08 07:30:00', $format(AdaptiveLearning::serveReadyAt($night)));

        $this->assertTrue(AdaptiveLearning::isServeBlocked($morning, CarbonImmutable::parse('2026-10-07 20:00:00', $tz)));
        $this->assertFalse(AdaptiveLearning::isServeBlocked($morning, CarbonImmutable::parse('2026-10-08 05:00:00', $tz)));
    }

    public function test_weak_focus_blocked_copy(): void
    {
        $tz = \Modules\QuestionBank\Support\MemoryStability::timezone();
        $now = CarbonImmutable::parse('2026-10-07 20:00:00', $tz);
        $none = AdaptiveLearning::weakFocusBlocked(0, null, $now);
        $this->assertSame('none_in_scope', $none['reason']);

        $tomorrow = AdaptiveLearning::weakFocusBlocked(2, CarbonImmutable::parse('2026-10-08 04:00:00', $tz), $now);
        $this->assertSame('resting_until_tomorrow', $tomorrow['reason']);
        $this->assertStringContainsString('ngày mai', $tomorrow['message']);

        $later = AdaptiveLearning::weakFocusBlocked(1, CarbonImmutable::parse('2026-10-11 04:00:00', $tz), $now);
        $this->assertSame('resting_later', $later['reason']);
        $this->assertStringContainsString('11/10', $later['message']);
    }

    public function test_retention_focus_blocked_copy(): void
    {
        $tz = \Modules\QuestionBank\Support\MemoryStability::timezone();
        $now = CarbonImmutable::parse('2026-10-07 20:00:00', $tz);

        $none = AdaptiveLearning::retentionFocusBlocked(0, null, $now);
        $this->assertSame('none_in_scope', $none['reason']);
        $this->assertStringContainsString('củng cố', $none['message']);

        $tomorrow = AdaptiveLearning::retentionFocusBlocked(2, CarbonImmutable::parse('2026-10-08 04:00:00', $tz), $now);
        $this->assertSame('resting_until_tomorrow', $tomorrow['reason']);
        $this->assertStringContainsString('ngày mai', $tomorrow['message']);
    }

}
