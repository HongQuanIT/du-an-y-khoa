<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Thang độ bền adaptive V2 (mophong):
 * S ∈ {1, 3, 7, 14, 30, 60} ngày.
 * R = 0,9 ^ (t / S) — tại t = S còn nhớ 90%.
 *
 * Quy tắc đổi bậc:
 * - Lần đầu: đúng → bậc 2 (3 ngày); sai → bậc 1 (1 ngày)
 * - Sai: về bậc 1
 * - Đúng khi đã đến hạn (t ≥ S): lên 1 bậc (trần bậc 6)
 * - Đúng khi chưa đến hạn (t < S): giữ bậc
 */
final class MemoryStability
{
    /** @var list<int> */
    public const array LADDER_DAYS = [1, 3, 7, 14, 30, 60];

    public const float RETENTION_BASE = 0.9;

    public static function stabilityForStep(int $step): float
    {
        $index = max(1, min(count(self::LADDER_DAYS), $step)) - 1;

        return (float) self::LADDER_DAYS[$index];
    }

    /**
     * Map S đang lưu (kể cả dữ liệu cũ ×2/×0.3) về bậc gần nhất trên thang.
     */
    public static function stepFromDays(?float $stabilityDays): ?int
    {
        if ($stabilityDays === null || $stabilityDays <= 0) {
            return null;
        }

        $bestStep = 1;
        $bestDiff = INF;
        foreach (self::LADDER_DAYS as $i => $days) {
            $diff = abs($days - $stabilityDays);
            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $bestStep = $i + 1;
            }
        }

        return $bestStep;
    }

    public static function afterGrade(
        ?float $previousStabilityDays,
        bool $correct,
        ?DateTimeInterface $lastGradedAt = null,
        ?DateTimeInterface $answeredAt = null,
    ): float {
        $prevStep = self::stepFromDays($previousStabilityDays);

        if ($prevStep === null || $lastGradedAt === null || $answeredAt === null) {
            return self::stabilityForStep($correct ? 2 : 1);
        }

        if (! $correct) {
            return self::stabilityForStep(1);
        }

        $s = self::stabilityForStep($prevStep);
        $t = self::elapsedDays($lastGradedAt, $answeredAt);

        if ($t >= $s) {
            return self::stabilityForStep(min($prevStep + 1, count(self::LADDER_DAYS)));
        }

        return $s;
    }

    public static function elapsedDays(DateTimeInterface $lastGradedAt, DateTimeInterface $now): float
    {
        $seconds = CarbonImmutable::parse($lastGradedAt)->diffInSeconds(CarbonImmutable::parse($now), absolute: true);

        return max(0.0, $seconds / 86400);
    }

    public static function retention(float $stabilityDays, float $elapsedDays): float
    {
        $step = self::stepFromDays($stabilityDays) ?? 1;
        $s = self::stabilityForStep($step);

        return pow(self::RETENTION_BASE, max(0.0, $elapsedDays) / $s);
    }

    public static function urgency(float $stabilityDays, float $elapsedDays): float
    {
        return 1.0 - self::retention($stabilityDays, $elapsedDays);
    }

    public static function isDue(?float $stabilityDays, ?DateTimeInterface $lastGradedAt, DateTimeInterface $now): bool
    {
        if ($stabilityDays === null || $lastGradedAt === null) {
            return false;
        }

        $step = self::stepFromDays($stabilityDays) ?? 1;
        $s = self::stabilityForStep($step);

        return self::elapsedDays($lastGradedAt, $now) >= $s;
    }

    /** Mốc đến hạn ôn: last_graded_at + S (thang bậc). */
    public static function dueAt(?float $stabilityDays, ?DateTimeInterface $lastGradedAt): ?CarbonImmutable
    {
        if ($stabilityDays === null || $lastGradedAt === null) {
            return null;
        }

        $step = self::stepFromDays($stabilityDays) ?? 1;
        $s = self::stabilityForStep($step);

        return CarbonImmutable::parse($lastGradedAt)->addSeconds((int) round($s * 86400));
    }
}
