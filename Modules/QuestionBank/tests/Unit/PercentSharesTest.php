<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Unit;

use Modules\QuestionBank\Services\QuestionSessionInsights;
use PHPUnit\Framework\TestCase;

final class PercentSharesTest extends TestCase
{
    public function test_equal_thirds_add_up_to_one_hundred(): void
    {
        $shares = QuestionSessionInsights::percentShares([
            'unaided' => 2,
            'hint' => 2,
            'wrong' => 2,
            'not_done' => 0,
        ]);

        $this->assertSame(100, array_sum($shares));
        $this->assertSame(0, $shares['not_done']);
        $this->assertSame(34, $shares['unaided']);
        $this->assertSame(33, $shares['hint']);
        $this->assertSame(33, $shares['wrong']);
    }

    public function test_exact_halves_stay_at_fifty(): void
    {
        $shares = QuestionSessionInsights::percentShares([
            'unaided' => 0,
            'hint' => 0,
            'wrong' => 2,
            'not_done' => 2,
        ]);

        $this->assertSame([
            'unaided' => 0,
            'hint' => 0,
            'wrong' => 50,
            'not_done' => 50,
        ], $shares);
    }
}
