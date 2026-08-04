<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Analytics\Actions\RecalculateTopicMasteryAction;
use Modules\QuestionBank\Enums\UserQuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\QuestionStatus as UserQuestionStatusModel;
use Modules\StudyPlan\Models\StudyPlanTask;

/**
 * Use case: record one answer inside a plan-driven session.
 *
 * Owns the write side of a study session: the attempt, the session counters,
 * per-question status, task progress and the topic rollup. Re-answering the
 * same question updates the existing attempt instead of adding a second one.
 */
final class AnswerPlanQuestionAction
{
    use AsAction;

    public function __construct(
        private readonly CompletePlanTaskAction $completeTask,
        private readonly RecalculateTopicMasteryAction $recalculateMastery,
    ) {}

    /**
     * @param  array<int, int>  $selectedOptionIds
     */
    public function handle(
        StudyPlanTask $task,
        QuestionSession $session,
        Question $question,
        array $selectedOptionIds,
        int $timeSpentSeconds = 0,
    ): QuestionAttempt {
        $isCorrect = $this->isCorrect($question, $selectedOptionIds);
        $userId = $session->user_id;
        $now = Carbon::now();

        $attempt = DB::transaction(function () use (
            $task, $session, $question, $selectedOptionIds, $timeSpentSeconds, $isCorrect, $userId, $now
        ): QuestionAttempt {
            $attempt = QuestionAttempt::query()
                ->where('session_id', $session->getKey())
                ->where('question_id', $question->getKey())
                ->first();

            $flagged = (bool) ($attempt?->flagged
                ?? (($session->annotations ?? [])[(string) $question->getKey()]['flagged'] ?? false));

            $attempt = QuestionAttempt::updateOrCreate(
                ['session_id' => $session->getKey(), 'question_id' => $question->getKey()],
                [
                    'user_id' => $userId,
                    'selected_option_ids' => $selectedOptionIds,
                    'is_correct' => $isCorrect,
                    'used_hint' => false,
                    'time_spent_seconds' => $timeSpentSeconds,
                    'flagged' => $flagged,
                    'answered_at' => $now,
                ],
            );

            $this->syncQuestionStatus($userId, $question, $isCorrect, $now);
            $this->syncSessionCounters($session);
            $this->syncTaskProgress($task, $session);

            return $attempt;
        });

        $this->recalculateMastery->handle($userId);

        if ($this->hasReachedTarget($task, $session)) {
            $this->completeTask->handle($task->refresh());
        }

        return $attempt;
    }

    /**
     * @param  array<int, int>  $selectedOptionIds
     */
    private function isCorrect(Question $question, array $selectedOptionIds): bool
    {
        $correctIds = $question->options
            ->where('is_correct', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $selected = array_values(array_unique(array_map('intval', $selectedOptionIds)));
        sort($selected);

        return $correctIds !== [] && $correctIds === $selected;
    }

    private function syncQuestionStatus(int $userId, Question $question, bool $isCorrect, Carbon $now): void
    {
        $status = UserQuestionStatusModel::firstOrNew([
            'user_id' => $userId,
            'question_id' => $question->getKey(),
        ]);

        $status->fill([
            'status' => $isCorrect ? UserQuestionStatus::Correct : UserQuestionStatus::Incorrect,
            'attempts_count' => ($status->attempts_count ?? 0) + 1,
            'last_attempt_at' => $now,
            'last_correct_at' => $isCorrect ? $now : $status->last_correct_at,
        ])->save();
    }

    private function syncSessionCounters(QuestionSession $session): void
    {
        $attempts = QuestionAttempt::query()
            ->where('session_id', $session->getKey())
            ->get(['is_correct']);

        $session->forceFill([
            'answered_count' => $attempts->count(),
            'correct_count' => $attempts->where('is_correct', true)->count(),
        ])->save();
    }

    private function syncTaskProgress(StudyPlanTask $task, QuestionSession $session): void
    {
        $task->forceFill([
            'done' => min($task->target, $session->answered_count),
        ])->save();
    }

    private function hasReachedTarget(StudyPlanTask $task, QuestionSession $session): bool
    {
        return $session->answered_count >= min($task->target, $session->total);
    }
}
