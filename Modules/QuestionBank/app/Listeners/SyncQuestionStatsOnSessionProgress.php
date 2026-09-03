<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Listeners;

use Modules\QuestionBank\Actions\SyncQuestionStatsAction;
use Modules\QuestionBank\Data\QuestionSessionProgressed;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;

final class SyncQuestionStatsOnSessionProgress
{
    public function __construct(private readonly SyncQuestionStatsAction $syncStats) {}

    public function handle(QuestionSessionProgressed $event): void
    {
        if (! $event->completed) {
            return;
        }

        $session = QuestionSession::query()->find($event->sessionId);
        if ($session === null) {
            return;
        }

        $questionIds = QuestionAttempt::query()
            ->where('session_id', $session->getKey())
            ->pluck('question_id')
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();

        if ($questionIds === []) {
            return;
        }

        $this->syncStats->syncForQuestionIds($questionIds);
    }
}
