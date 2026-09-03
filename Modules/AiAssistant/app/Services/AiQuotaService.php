<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use App\Models\User;
use App\Support\Enums\Entitlement;
use Illuminate\Support\Carbon;
use Modules\AiAssistant\Exceptions\QuotaExceededException;
use Modules\AiAssistant\Models\AiUsage;

/**
 * Daily free-tier quota for AI Tutor.
 *
 * The `ai_usage` table is the source of truth; increments are atomic at the DB
 * level so concurrent requests cannot over-spend. Users with the `ai.tutor`
 * entitlement (Premium/staff) are unlimited and never touch the ledger.
 */
final class AiQuotaService
{
    public function isUnlimited(User $user): bool
    {
        return $user->hasEntitlement(Entitlement::AiTutor->value);
    }

    public function limit(): int
    {
        return (int) config('aiassistant.free_daily_limit', 10);
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

        return max(0, $this->limit() - $this->used($user));
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

        $today = Carbon::today()->toDateString();
        $limit = $this->limit();

        // Ensure the row exists without racing on the unique key.
        AiUsage::query()->firstOrCreate(
            ['user_id' => $user->getKey(), 'date' => $today],
            ['count' => 0],
        );

        // Atomic guarded increment: only bumps while still under the limit.
        $affected = AiUsage::query()
            ->where('user_id', $user->getKey())
            ->where('date', $today)
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
            ->where('date', Carbon::today()->toDateString())
            ->where('count', '>', 0)
            ->decrement('count');
    }

    /** @return array{remaining: int|null, limit: int, unlimited: bool, resets_at: string} */
    public function snapshot(User $user): array
    {
        $unlimited = $this->isUnlimited($user);

        return [
            'remaining' => $unlimited ? null : $this->remaining($user),
            'limit' => $this->limit(),
            'unlimited' => $unlimited,
            'resets_at' => Carbon::tomorrow()->startOfDay()->toIso8601String(),
        ];
    }
}
