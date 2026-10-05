<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Adaptive V2 constants + pure helpers (mophong khung Lọc → Phân nhóm → Phân suất).
 * Độ bền S vẫn dùng {@see MemoryStability}; “đến hạn” = t ≥ S.
 */
final class AdaptiveLearning
{
    public const string PIPELINE = 'filter_group_quota_v2';

    public const int WEAK_WINDOW = 5;

    public const float WEAK_THRESHOLD = 0.5;

    public const int COOLDOWN_HOURS = 20;

    public const int MIN_RESPONSE_MS = 5000;

    public const float NEW_SHARE_LOW = 0.3;

    public const float NEW_SHARE_MID = 0.2;

    public const float NEW_SHARE_HIGH = 0.1;

    public const int MID_BACKLOG_FACTOR = 1;

    public const int HIGH_BACKLOG_FACTOR = 3;

    public const float DIVERSITY_FACTOR = 2.0;

    /** Sai liên tiếp ≥ 3 → tầng vừa (72h + 2 phiên). */
    public const int THRASH_MILD_STREAK = 3;

    /** Sai liên tiếp ≥ 5 → tầng nặng (7 ngày). */
    public const int THRASH_SEVERE_STREAK = 5;

    public const int THRASH_MILD_SESSIONS = 2;

    public const int THRASH_MILD_HOURS = 72;

    public const int THRASH_SEVERE_DAYS = 7;

    /**
     * @param  list<bool>  $recentResults  cũ → mới, tối đa WEAK_WINDOW
     */
    public static function weakness(array $recentResults): float
    {
        $wrong = count(array_filter($recentResults, static fn (bool $r): bool => ! $r));

        return ($wrong + 1) / (count($recentResults) + 2);
    }

    public static function isDue(?float $stabilityDays, ?DateTimeInterface $lastGradedAt, DateTimeInterface $now): bool
    {
        return MemoryStability::isDue($stabilityDays, $lastGradedAt, $now);
    }

    public static function dueAt(?float $stabilityDays, ?DateTimeInterface $lastGradedAt): ?CarbonImmutable
    {
        return MemoryStability::dueAt($stabilityDays, $lastGradedAt);
    }

    public static function retention(?float $stabilityDays, ?DateTimeInterface $lastGradedAt, DateTimeInterface $now): ?float
    {
        if ($stabilityDays === null || $stabilityDays <= 0 || $lastGradedAt === null) {
            return null;
        }

        return MemoryStability::retention($stabilityDays, MemoryStability::elapsedDays($lastGradedAt, $now));
    }

    /**
     * @return array{share: float, band: 'new'|'low'|'mid'|'high', count: int}
     */
    public static function newQuestionQuota(int $duePool, int $unseen, int $seen, int $size): array
    {
        if ($size <= 0 || $unseen <= 0) {
            return ['share' => 0.0, 'band' => 'low', 'count' => 0];
        }

        if ($seen === 0) {
            return ['share' => 1.0, 'band' => 'new', 'count' => min($size, $unseen)];
        }

        if ($duePool >= self::HIGH_BACKLOG_FACTOR * $size) {
            $share = self::NEW_SHARE_HIGH;
            $band = 'high';
        } elseif ($duePool >= self::MID_BACKLOG_FACTOR * $size) {
            $share = self::NEW_SHARE_MID;
            $band = 'mid';
        } else {
            $share = self::NEW_SHARE_LOW;
            $band = 'low';
        }

        $count = min($unseen, max(0, (int) round($share * $size)));

        return ['share' => $share, 'band' => $band, 'count' => $count];
    }

    public static function isValidGradedResponse(int $timeSpentSeconds): bool
    {
        return ($timeSpentSeconds * 1000) >= self::MIN_RESPONSE_MS;
    }

    /**
     * @param  list<bool>  $previousRecent
     * @return array{recent_results: list<bool>, wrong_streak: int, thrash_blocked_until: CarbonImmutable|null}
     */
    public static function afterGrade(
        array $previousRecent,
        int $previousStreak,
        bool $isCorrect,
        DateTimeInterface $answeredAt,
        DateTimeInterface $now,
    ): array {
        $recent = [...array_values($previousRecent), $isCorrect];
        if (count($recent) > self::WEAK_WINDOW) {
            $recent = array_slice($recent, -self::WEAK_WINDOW);
        }

        $streak = $isCorrect ? 0 : $previousStreak + 1;
        $blockedUntil = null;

        if (! $isCorrect && $streak >= self::THRASH_SEVERE_STREAK) {
            $blockedUntil = CarbonImmutable::parse($answeredAt)->addDays(self::THRASH_SEVERE_DAYS);
        } elseif (! $isCorrect && $streak >= self::THRASH_MILD_STREAK) {
            // Mở khóa khi đủ cả 2 phiên và 72h — lưu mốc giờ; số phiên kiểm tra lúc chọn câu.
            $blockedUntil = CarbonImmutable::parse($answeredAt)->addHours(self::THRASH_MILD_HOURS);
        }

        return [
            'recent_results' => $recent,
            'wrong_streak' => $streak,
            'thrash_blocked_until' => $blockedUntil,
        ];
    }

    /**
     * Tầng nặng (≥5): theo thrash_blocked_until (7 ngày).
     * Tầng vừa (≥3): block khi chưa hết 72h HOẶC chưa đủ 2 phiên sau last_graded_at.
     */
    public static function isThrashBlocked(
        ?DateTimeInterface $blockedUntil,
        int $wrongStreak,
        int $sessionsSinceLastGraded,
        DateTimeInterface $now,
    ): bool {
        if ($wrongStreak < self::THRASH_MILD_STREAK) {
            return false;
        }

        $nowAt = CarbonImmutable::parse($now);

        if ($wrongStreak >= self::THRASH_SEVERE_STREAK) {
            return $blockedUntil !== null && $nowAt->lessThan(CarbonImmutable::parse($blockedUntil));
        }

        $timeBlocked = $blockedUntil !== null && $nowAt->lessThan(CarbonImmutable::parse($blockedUntil));
        $sessionBlocked = $sessionsSinceLastGraded < self::THRASH_MILD_SESSIONS;

        return $timeBlocked || $sessionBlocked;
    }
}
