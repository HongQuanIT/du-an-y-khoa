<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use App\Models\User;
use App\Support\Auth\Staff;
use App\Support\Enums\Entitlement;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Modules\AiAssistant\Exceptions\QuotaExceededException;
use Modules\AiAssistant\Models\AiUsage;
use Modules\AiAssistant\Support\AiTutorSettings;

/**
 * Daily AI Tutor quota ledger (`ai_usage`).
 *
 * Tiers:
 * - Staff: unlimited (no ledger writes)
 * - Premium (`ai.tutor`): soft-cap `premium_daily_limit` (default 100)
 * - Free: `free_daily_limit` (default 10)
 */
final class AiQuotaService
{
    public function isUnlimited(User $user): bool
    {
        return Staff::isStaff($user);
    }

    public function isPremium(User $user): bool
    {
        return ! $this->isUnlimited($user)
            && $user->hasEntitlement(Entitlement::AiTutor->value);
    }

    public function limitFor(User $user): int
    {
        if ($this->isUnlimited($user)) {
            return 0;
        }

        if ($this->isPremium($user)) {
            return AiTutorSettings::premiumDailyLimit();
        }

        return AiTutorSettings::freeDailyLimit();
    }

    /** @deprecated Prefer limitFor(User) — kept for callers that only need Free default. */
    public function limit(): int
    {
        return AiTutorSettings::freeDailyLimit();
    }

    public function used(User $user): int
    {
        return (int) (AiUsage::query()
            ->where('user_id', $user->getKey())
            ->whereDate('date', Carbon::today())
            ->value('count') ?? 0);
    }

    public function remaining(User $user): int
    {
        if ($this->isUnlimited($user)) {
            return PHP_INT_MAX;
        }

        return max(0, $this->limitFor($user) - $this->used($user));
    }

    public function hasQuota(User $user): bool
    {
        return $this->isUnlimited($user) || $this->remaining($user) > 0;
    }

    /**
     * Atomically consume one unit. Returns silently for unlimited users.
     *
     * @throws QuotaExceededException when the daily limit is reached.
     */
    public function consume(User $user): void
    {
        if ($this->isUnlimited($user)) {
            return;
        }

        $today = Carbon::today();
        $limit = $this->limitFor($user);

        $this->ensureUsageRow($user, $today);

        // Atomic guarded increment: only bumps while still under the limit.
        $affected = AiUsage::query()
            ->where('user_id', $user->getKey())
            ->whereDate('date', $today)
            ->where('count', '<', $limit)
            ->increment('count');

        if ($affected === 0) {
            throw new QuotaExceededException('Đã hết lượt AI Tutor hôm nay.');
        }
    }

    /** Give back one unit after a provider failure (never below zero). */
    public function refund(User $user): void
    {
        if ($this->isUnlimited($user)) {
            return;
        }

        AiUsage::query()
            ->where('user_id', $user->getKey())
            ->whereDate('date', Carbon::today())
            ->where('count', '>', 0)
            ->decrement('count');
    }

    private function ensureUsageRow(User $user, Carbon $today): void
    {
        $exists = AiUsage::query()
            ->where('user_id', $user->getKey())
            ->whereDate('date', $today)
            ->exists();

        if ($exists) {
            return;
        }

        try {
            AiUsage::query()->create([
                'user_id' => $user->getKey(),
                'date' => $today->toDateString(),
                'count' => 0,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Concurrent request created the row.
        }
    }

    /** @return array{remaining: int|null, limit: int, unlimited: bool, resets_at: string} */
    public function snapshot(User $user): array
    {
        $unlimited = $this->isUnlimited($user);

        return [
            'remaining' => $unlimited ? null : $this->remaining($user),
            'limit' => $this->limitFor($user),
            'unlimited' => $unlimited,
            'resets_at' => Carbon::tomorrow()->startOfDay()->toIso8601String(),
        ];
    }
}
