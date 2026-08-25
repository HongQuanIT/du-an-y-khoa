<?php

declare(strict_types=1);

namespace Modules\Analytics\Listeners;

use Illuminate\Support\Carbon;
use Modules\Analytics\Actions\RecalculateDailyLearningStatsAction;
use Modules\Analytics\Support\DashboardCache;
use Modules\QuestionBank\Data\QuestionSessionProgressed;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;

final class UpdateDailyLearningStats
{
    public function __construct(private readonly RecalculateDailyLearningStatsAction $recalculate) {}

    public function handle(QuestionSessionProgressed $event): void
    {
        if ($event->completed) {
            $session = QuestionSession::query()->find($event->sessionId);
            $dates = QuestionAttempt::query()
                ->where('session_id', $event->sessionId)
                ->whereNotNull('answered_at')
                ->pluck('answered_at')
                ->map(fn ($date): string => Carbon::parse($date)->toDateString());

            if ($session?->updated_at !== null) {
                $dates->push($session->updated_at->toDateString());
            }

            $this->recalculate->handle($event->userId, $dates->unique()->values()->all());
            DashboardCache::forget($event->userId);
        }
    }
}
