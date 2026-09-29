<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Unit;

use Modules\QuestionBank\Support\MemoryStability;
use PHPUnit\Framework\TestCase;

final class MemoryStabilityTest extends TestCase
{
    public function test_first_correct_doubles_the_one_day_start(): void
    {
        $this->assertSame(2.0, MemoryStability::afterGrade(null, true));
    }

    public function test_first_wrong_is_clamped_up_to_half_a_day(): void
    {
        $this->assertSame(0.5, MemoryStability::afterGrade(null, false));
    }

    public function test_later_grades_multiply_then_clamp(): void
    {
        $this->assertSame(4.0, MemoryStability::afterGrade(2.0, true));
        $this->assertSame(1.2, MemoryStability::afterGrade(4.0, false));
        $this->assertSame(365.0, MemoryStability::afterGrade(200.0, true));
        $this->assertSame(0.5, MemoryStability::afterGrade(0.5, false));
    }

    public function test_urgency_is_higher_when_stability_is_lower(): void
    {
        $fragile = MemoryStability::urgency(0.5, 10);
        $durable = MemoryStability::urgency(32, 10);

        $this->assertGreaterThan(0.9, $fragile);
        $this->assertLessThan(0.4, $durable);
    }
}
