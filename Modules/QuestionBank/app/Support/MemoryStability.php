<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Forgetting curve V1 (docs/adaptive-session-algorithm.md §4.2).
 *
 * R = exp(−t / S). Urgency = 1 − R.
 * First grade starts at 1 day, then ×2 if correct and ×0.3 if wrong, clamped to [0.5, 365].
 */
final class MemoryStability
{
    public const float INITIAL_DAYS = 1.0;

    public const float CORRECT_FACTOR = 2.0;

    public const float WRONG_FACTOR = 0.3;

    public const float MIN_DAYS = 0.5;

    public const float MAX_DAYS = 365.0;

    public static function afterGrade(?float $stabilityDays, bool $correct): float
    {
        $base = $stabilityDays ?? self::INITIAL_DAYS;
        $factor = $correct ? self::CORRECT_FACTOR : self::WRONG_FACTOR;

        return self::clamp($base * $factor);
    }

    public static function elapsedDays(DateTimeInterface $lastGradedAt, DateTimeInterface $now): float
    {
        $seconds = CarbonImmutable::parse($lastGradedAt)->diffInSeconds(CarbonImmutable::parse($now), absolute: true);

        return max(0.0, $seconds / 86400);
    }

    public static function retention(float $stabilityDays, float $elapsedDays): float
    {
        $stability = max(self::MIN_DAYS, $stabilityDays);

        return exp(-max(0.0, $elapsedDays) / $stability);
    }

    public static function urgency(float $stabilityDays, float $elapsedDays): float
    {
        return 1.0 - self::retention($stabilityDays, $elapsedDays);
    }

    public static function clamp(float $days): float
    {
        return max(self::MIN_DAYS, min(self::MAX_DAYS, $days));
    }
}
