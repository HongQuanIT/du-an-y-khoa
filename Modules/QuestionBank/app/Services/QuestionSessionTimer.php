<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\QuestionSession;

/** Authoritative server-side countdown, including pause/resume periods. */
final class QuestionSessionTimer
{
    public const REMAINING_KEY = 'timer_remaining_seconds';

    public const STARTED_AT_KEY = 'timer_started_at';

    public function remainingSeconds(
        QuestionSession $session,
        ?CarbonInterface $at = null,
    ): ?int {
        if ($session->mode !== SessionMode::Exam || $session->time_limit_seconds === null) {
            return null;
        }

        $state = $session->paused_state ?? [];
        $storedRemaining = $this->storedRemaining($state);

        if ($session->status === SessionStatus::Paused) {
            if ($storedRemaining !== null) {
                return $storedRemaining;
            }

            // Compatibility for sessions paused before timer snapshots existed:
            // their last update is the best available authoritative pause time.
            $legacyDeadline = $session->created_at?->copy()->addSeconds($session->time_limit_seconds);

            if ($legacyDeadline !== null && $session->updated_at !== null) {
                return max(0, (int) $session->updated_at->diffInSeconds($legacyDeadline, false));
            }
        }

        $at ??= now();
        $startedAt = $state[self::STARTED_AT_KEY] ?? null;

        if ($storedRemaining !== null && is_string($startedAt) && $startedAt !== '') {
            $deadline = Carbon::parse($startedAt)->addSeconds($storedRemaining);

            return max(0, (int) $at->diffInSeconds($deadline, false));
        }

        $deadline = $session->created_at?->copy()->addSeconds($session->time_limit_seconds);

        return $deadline === null
            ? $session->time_limit_seconds
            : max(0, (int) $at->diffInSeconds($deadline, false));
    }

    /** @param array<string, mixed> $state */
    private function storedRemaining(array $state): ?int
    {
        $remaining = $state[self::REMAINING_KEY] ?? null;

        return is_numeric($remaining) ? max(0, (int) $remaining) : null;
    }
}
