<?php

declare(strict_types=1);

namespace Modules\Analytics\Actions;

use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Facades\DB;
use Modules\AiAssistant\Models\AiThread;
use Modules\AiAssistant\Models\AiUsage;
use Modules\Analytics\Jobs\ResetLearnerProgressJob;
use Modules\Analytics\Models\DailyLearningStat;
use Modules\Analytics\Models\TopicMastery;
use Modules\Analytics\Support\DashboardCache;
use Modules\Notification\Models\StreakWarningLog;
use Modules\Notification\Models\StudyPlanReminderLog;
use Modules\Personalization\Models\Bookmark;
use Modules\Personalization\Models\BookmarkFolder;
use Modules\QuestionBank\Actions\SyncQuestionStatsAction;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionFeedback;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\QuestionStatus;
use Modules\StudyPlan\Models\StudyPlan;

/**
 * Wipe learner progress so the user can study again like a new account
 * (sessions, rollups, personalization) without touching billing/classroom.
 *
 * @phpstan-type ResetResult array{queued: bool, question_ids: list<string>, attempt_count: int}
 */
final class ResetLearnerProgressAction
{
    use AsAction;

    public const ASYNC_ATTEMPT_THRESHOLD = 5000;

    public function __construct(
        private readonly SyncQuestionStatsAction $syncQuestionStats,
    ) {}

    /**
     * @return ResetResult
     */
    public function handle(User $user, bool $forceSync = false): array
    {
        $user->refresh();

        $userId = (int) $user->getKey();
        $attemptCount = QuestionAttempt::query()->where('user_id', $userId)->count();
        $questionIds = $this->collectAffectedQuestionIds($userId);

        if (! $forceSync && $attemptCount > self::ASYNC_ATTEMPT_THRESHOLD) {
            $user->forceFill(['learning_progress_reset_at' => now()])->save();
            ResetLearnerProgressJob::dispatch($userId, $questionIds);

            Auditor::record(
                AuditAction::LearningProgressReset,
                $user,
                $user,
                metadata: [
                    'queued' => true,
                    'attempt_count' => $attemptCount,
                    'question_count' => count($questionIds),
                ],
            );

            return [
                'queued' => true,
                'question_ids' => $questionIds,
                'attempt_count' => $attemptCount,
            ];
        }

        $this->wipe($user, $questionIds);

        Auditor::record(
            AuditAction::LearningProgressReset,
            $user,
            $user,
            metadata: [
                'queued' => false,
                'attempt_count' => $attemptCount,
                'question_count' => count($questionIds),
            ],
        );

        return [
            'queued' => false,
            'question_ids' => $questionIds,
            'attempt_count' => $attemptCount,
        ];
    }

    /**
     * @param  list<string>  $questionIds
     */
    public function wipe(User $user, array $questionIds = []): void
    {
        $userId = (int) $user->getKey();

        if ($questionIds === []) {
            $questionIds = $this->collectAffectedQuestionIds($userId);
        }

        DB::transaction(function () use ($user, $userId): void {
            QuestionFeedback::query()
                ->where('user_id', $userId)
                ->whereNotNull('question_session_id')
                ->update(['question_session_id' => null]);

            QuestionSession::withTrashed()->where('user_id', $userId)->forceDelete();

            QuestionStatus::query()->where('user_id', $userId)->delete();
            DailyLearningStat::query()->where('user_id', $userId)->delete();
            TopicMastery::query()->where('user_id', $userId)->delete();
            StudyPlan::query()->where('user_id', $userId)->delete();
            BookmarkFolder::query()->where('user_id', $userId)->delete();
            Bookmark::query()->where('user_id', $userId)->delete();
            AiThread::query()->where('user_id', $userId)->delete();
            AiUsage::query()->where('user_id', $userId)->delete();
            StudyPlanReminderLog::query()->where('user_id', $userId)->delete();
            StreakWarningLog::query()->where('user_id', $userId)->delete();

            $user->forceFill(['learning_progress_reset_at' => now()])->save();
        });

        DashboardCache::forget($userId);

        if ($questionIds !== []) {
            foreach (array_chunk($questionIds, 200) as $chunk) {
                $this->syncQuestionStats->syncForQuestionIds($chunk);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function collectAffectedQuestionIds(int $userId): array
    {
        $fromAttempts = QuestionAttempt::query()
            ->where('user_id', $userId)
            ->distinct()
            ->pluck('question_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        $fromStatus = QuestionStatus::query()
            ->where('user_id', $userId)
            ->pluck('question_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        return array_values(array_unique([...$fromAttempts, ...$fromStatus]));
    }
}
