<?php

declare(strict_types=1);

namespace Modules\Analytics\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Modules\Analytics\Models\DailyLearningStat;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;

/** One-time lazy backfill for learners who studied before daily rollups shipped. */
final class EnsureDailyLearningStatsAction
{
    use AsAction;

    public function __construct(private readonly RecalculateDailyLearningStatsAction $recalculate) {}

    public function handle(User $user): void
    {
        if (DailyLearningStat::query()->where('user_id', $user->getKey())->exists()) {
            return;
        }

        if (
            QuestionAttempt::query()->where('user_id', $user->getKey())->whereNotNull('answered_at')->exists()
            || QuestionSession::query()->where('user_id', $user->getKey())->where('status', 'completed')->exists()
        ) {
            $this->recalculate->handle((int) $user->getKey());
        }
    }
}
