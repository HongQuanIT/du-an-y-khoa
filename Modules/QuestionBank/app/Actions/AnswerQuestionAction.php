<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\QuestionBank\Data\QuestionSessionProgressed;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Enums\UserQuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\QuestionStatus as UserQuestionStatusModel;
use Modules\QuestionBank\Services\QuestionGrader;
use RuntimeException;

/**
 * Record one answer inside a Q-Bank-owned session.
 *
 * Study answers are graded immediately. Exam answers are only autosaved; the
 * complete action performs authoritative bulk grading on explicit finish or
 * timeout.
 */
final class AnswerQuestionAction
{
    use AsAction;

    public function __construct(
        private readonly CompleteQuestionSessionAction $completeSession,
        private readonly QuestionGrader $grader,
    ) {}

    /**
     * @param  array<int, int>  $selectedOptionIds
     */
    public function handle(
        QuestionSession $session,
        Question $question,
        array $selectedOptionIds,
        int $timeSpentSeconds = 0,
        bool $autoComplete = true,
    ): QuestionAttempt {
        if ($timeSpentSeconds < 0) {
            throw new InvalidArgumentException('Thời gian làm câu hỏi không được âm.');
        }

        $selectedOptionIds = $this->normalizeOptionIds($selectedOptionIds);

        /** @var array{0: QuestionAttempt, 1: QuestionSession} $result */
        $result = DB::transaction(function () use (
            $session,
            $question,
            $selectedOptionIds,
            $timeSpentSeconds,
        ): array {
            $currentSession = QuestionSession::query()
                ->lockForUpdate()
                ->findOrFail($session->getKey());

            $this->assertMutableSession($currentSession);
            $this->assertQuestionBelongsToSession($currentSession, $question);
            $this->assertOptionsBelongToQuestion($question, $selectedOptionIds);

            if ($currentSession->mode === SessionMode::Study && $selectedOptionIds === []) {
                throw new InvalidArgumentException('Chế độ học tập yêu cầu chọn ít nhất một đáp án.');
            }

            $existing = QuestionAttempt::query()
                ->where('session_id', $currentSession->getKey())
                ->where('question_id', $question->getKey())
                ->first();

            if ($currentSession->mode === SessionMode::Study && $existing !== null) {
                throw new RuntimeException('Câu hỏi này đã được chấm trong chế độ học tập.');
            }

            $flagged = $existing instanceof QuestionAttempt
                ? $existing->flagged
                : (bool) (($currentSession->annotations ?? [])[(string) $question->getKey()]['flagged'] ?? false);
            $isStudy = $currentSession->mode === SessionMode::Study;
            $isCorrect = $isStudy
                ? $this->grader->isCorrect($question, $selectedOptionIds)
                : null;
            $now = Carbon::now();

            $attempt = QuestionAttempt::updateOrCreate(
                [
                    'session_id' => $currentSession->getKey(),
                    'question_id' => $question->getKey(),
                ],
                [
                    'user_id' => (int) $currentSession->user_id,
                    'selected_option_ids' => $selectedOptionIds,
                    'is_correct' => $isCorrect,
                    'used_hint' => false,
                    'time_spent_seconds' => $timeSpentSeconds,
                    'flagged' => $flagged,
                    'answered_at' => $now,
                ],
            );

            if ($isStudy && $this->liveQuestionExists($question)) {
                $this->syncQuestionStatus(
                    (int) $currentSession->user_id,
                    $question,
                    (bool) $isCorrect,
                    $now,
                    true,
                );
            }

            $this->syncSessionCounters($currentSession);

            return [$attempt, $currentSession];
        });

        [$attempt, $currentSession] = $result;

        if (
            $currentSession->mode === SessionMode::Study
            && $autoComplete
            && $this->isFullyAnswered($currentSession->refresh())
        ) {
            $this->completeSession->handle($currentSession);
        } elseif ($currentSession->mode === SessionMode::Study) {
            event(new QuestionSessionProgressed(
                userId: (int) $currentSession->user_id,
                sessionId: (string) $currentSession->getKey(),
                completed: false,
            ));
        }

        // Exam intentionally emits no progress/mastery event here because the
        // selected options have not been graded yet.
        return $attempt;
    }

    private function assertMutableSession(QuestionSession $session): void
    {
        if (! in_array($session->status, [SessionStatus::Active, SessionStatus::Paused], true)) {
            throw new RuntimeException('Phiên làm bài không còn ở trạng thái có thể cập nhật.');
        }
    }

    private function assertQuestionBelongsToSession(QuestionSession $session, Question $question): void
    {
        $questionIds = array_map('strval', $session->question_ids ?? []);

        if (! in_array((string) $question->getKey(), $questionIds, true)) {
            throw new InvalidArgumentException('Câu hỏi không thuộc phiên làm bài này.');
        }
    }

    /**
     * @param  array<int, int>  $selectedOptionIds
     */
    private function assertOptionsBelongToQuestion(Question $question, array $selectedOptionIds): void
    {
        if ($selectedOptionIds === []) {
            return;
        }

        $options = $question->relationLoaded('options')
            ? $question->getRelation('options')
            : $question->options()->get();
        $matching = $options->whereIn('id', $selectedOptionIds)->count();

        if ($matching !== count($selectedOptionIds)) {
            throw new InvalidArgumentException('Một hoặc nhiều đáp án không thuộc câu hỏi đã chọn.');
        }
    }

    /**
     * @param  array<int, int>  $selectedOptionIds
     * @return array<int, int>
     */
    private function normalizeOptionIds(array $selectedOptionIds): array
    {
        return array_values(array_unique($selectedOptionIds));
    }

    private function syncQuestionStatus(
        int $userId,
        Question $question,
        bool $isCorrect,
        Carbon $answeredAt,
        bool $incrementAttempts,
    ): void {
        $status = UserQuestionStatusModel::firstOrNew([
            'user_id' => $userId,
            'question_id' => $question->getKey(),
        ]);
        $attemptsCount = (int) ($status->attempts_count ?? 0);

        if ($incrementAttempts) {
            $attemptsCount++;
        } elseif (! $status->exists) {
            // Repair a missing rollup without double-counting the updated
            // attempt that already existed in this session.
            $attemptsCount = 1;
        }

        $answerStatus = $isCorrect ? UserQuestionStatus::Correct : UserQuestionStatus::Incorrect;
        $status->fill([
            // `marked` is the temporary bookmark fallback and therefore has
            // priority over the derived answer state until explicitly removed.
            'status' => $status->exists && $status->status === UserQuestionStatus::Marked
                ? UserQuestionStatus::Marked
                : $answerStatus,
            'attempts_count' => $attemptsCount,
            'last_attempt_at' => $answeredAt,
            'last_correct_at' => $isCorrect ? $answeredAt : $status->last_correct_at,
        ])->save();
    }

    private function liveQuestionExists(Question $question): bool
    {
        return Question::withTrashed()->whereKey($question->getKey())->exists();
    }

    private function syncSessionCounters(QuestionSession $session): void
    {
        $attempts = QuestionAttempt::query()
            ->where('session_id', $session->getKey())
            ->whereIn('question_id', $session->question_ids ?? [])
            ->get(['selected_option_ids', 'is_correct']);

        $session->forceFill([
            'answered_count' => $attempts
                ->filter(fn (QuestionAttempt $attempt): bool => ($attempt->selected_option_ids ?? []) !== [])
                ->count(),
            'correct_count' => $attempts->where('is_correct', true)->count(),
        ])->save();
    }

    private function isFullyAnswered(QuestionSession $session): bool
    {
        $questionIds = array_map('strval', $session->question_ids ?? []);

        if ($questionIds === []) {
            return false;
        }

        $answeredIds = QuestionAttempt::query()
            ->where('session_id', $session->getKey())
            ->whereIn('question_id', $questionIds)
            ->whereNotNull('is_correct')
            ->get(['question_id', 'selected_option_ids'])
            ->filter(fn (QuestionAttempt $attempt): bool => ($attempt->selected_option_ids ?? []) !== [])
            ->pluck('question_id')
            ->map(fn ($id) => (string) $id)
            ->unique();

        return $answeredIds->count() >= count($questionIds);
    }
}
