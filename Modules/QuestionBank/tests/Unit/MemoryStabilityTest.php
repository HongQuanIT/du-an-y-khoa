<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Unit;

use Carbon\CarbonImmutable;
use Modules\QuestionBank\Support\MemoryStability;
use Tests\TestCase;

final class MemoryStabilityTest extends TestCase
{
    public function test_ladder_first_grade_and_wrong_reset(): void
    {
        $this->assertSame(3.0, MemoryStability::afterGrade(null, true));
        $this->assertSame(1.0, MemoryStability::afterGrade(null, false));
        $this->assertSame(1.0, MemoryStability::afterGrade(14.0, false, now()->subDays(2), now()));
    }

    public function test_correct_on_time_promotes_early_keeps(): void
    {
        $last = CarbonImmutable::parse('2026-01-01 09:00:00');
        // S=3, t=4 ≥ 3 → lên bậc 3 (7 ngày)
        $this->assertSame(7.0, MemoryStability::afterGrade(3.0, true, $last, $last->addDays(4)));
        // S=3, t=1 < 3 → giữ 3
        $this->assertSame(3.0, MemoryStability::afterGrade(3.0, true, $last, $last->addDay()));
        // Trần bậc 6
        $this->assertSame(60.0, MemoryStability::afterGrade(60.0, true, $last, $last->addDays(80)));
    }

    public function test_retention_is_point_nine_pow(): void
    {
        // S=1, t=4 → 0.9^4 = 0.6561
        $this->assertEqualsWithDelta(0.6561, MemoryStability::retention(1.0, 4.0), 1e-9);
        // tại hạn t=S → 0.9
        $this->assertEqualsWithDelta(0.9, MemoryStability::retention(7.0, 7.0), 1e-9);
    }

    public function test_is_due_uses_ladder_s(): void
    {
        $now = CarbonImmutable::parse('2026-01-10 09:00:00');
        $this->assertTrue(MemoryStability::isDue(3.0, $now->subDays(3), $now));
        $this->assertFalse(MemoryStability::isDue(3.0, $now->subDays(2), $now));
    }

    public function test_due_at_is_last_graded_plus_stability(): void
    {
        $graded = now()->startOfDay();
        $due = MemoryStability::dueAt(7.0, $graded);

        $this->assertNotNull($due);
        $this->assertTrue($due->equalTo($graded->copy()->addDays(7)));
    }
}
