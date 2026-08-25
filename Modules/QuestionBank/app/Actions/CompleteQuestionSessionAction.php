<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Actions;

use App\Models\User;
use App\Support\Audit\AuditContext;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\QuestionBank\Data\QuestionSessionProgressed;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Enums\UserQuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\QuestionStatus as UserQuestionStatusModel;
use Modules\QuestionBank\Services\QuestionGrader;
use Modules\QuestionBank\Services\QuestionSessionSnapshots;
use RuntimeException;

/**
 * Authoritatively finish a Study or Exam session.
 *
 * Exam attempts are graded here in one transaction so answer autosaves never
 * reveal correctness early. Missing/cleared answers become `omitted` in the
 * per-user status rollup.
 */
final class CompleteQuestionSessionAction
{
    use AsAction;

    public function __construct(
        private readonly QuestionGrader $grader,
        private readonly QuestionSessionSnapshots $snapshots,
    ) {}

    public function handle(QuestionSession $session): QuestionSession
    {
        /** @var array{0: QuestionSession, 1: bool} $result */
        $result = DB::transaction(function () use ($session): array {
            $currentSession = QuestionSession::query()
                ->lockForUpdate()
                ->findOrFail($session->getKey());

            if ($currentSession->status === SessionStatus::Completed) {
                return [$currentSession, false];
            }

            if (! in_array($currentSession->status, [SessionStatus::Active, SessionStatus::Paused], true)) {
                throw new RuntimeException('Phiên làm bài không thể hoàn thành từ trạng thái hiện tại.');
            }

            $questionIds = array_values(array_unique(array_map(
                'strval',
                $currentSession->question_ids ?? [],
            )));
            $questions = $this->snapshots->questionMap($currentSession);
            $questionIds = array_values(array_filter(
                $questionIds,
                fn (string $questionId): bool => isset($questions[$questionId]),
            ));
            $attempts = QuestionAttempt::query()
                ->where('session_id', $currentSession->getKey())
                ->whereIn('question_id', $questionIds)
                ->get()
                ->keyBy(fn (QuestionAttempt $attempt): string => (string) $attempt->question_id);
            $now = Carbon::now();

            foreach ($questionIds as $questionId) {
                $question = $questions[$questionId] ?? null;

                // A deleted question cannot receive a status row. Keep the
                // immutable session snapshot intact and exclude it from grade.
                if (! $question instanceof Question) {
                    continue;
                }

                $attempt = $attempts->get($questionId);
                $selectedOptionIds = $this->selectedOptionIds($attempt);
                $wasGraded = $attempt?->is_correct !== null;

                if ($attempt instanceof QuestionAttempt && $selectedOptionIds !== []) {
                    if ($currentSession->mode === SessionMode::Exam || ! $wasGraded) {
                        $attempt->forceFill([
                            'is_correct' => $this->grader->isCorrect($question, $selectedOptionIds),
                        ])->save();
                    }

                    if ($this->liveQuestionExists($question)) {
                        $this->syncQuestionStatus(
                            (int) $currentSession->user_id,
                            $question,
                            $attempt->is_correct
                                ? UserQuestionStatus::Correct
                                : UserQuestionStatus::Incorrect,
                            $attempt->answered_at ?? $now,
                            $currentSession->mode === SessionMode::Exam || ! $wasGraded,
                        );
                    }

                    continue;
                }

                if ($attempt instanceof QuestionAttempt && $attempt->is_correct !== null) {
                    $attempt->forceFill(['is_correct' => null])->save();
                }

                if ($this->liveQuestionExists($question)) {
                    $this->syncQuestionStatus(
                        (int) $currentSession->user_id,
                        $question,
                        UserQuestionStatus::Omitted,
                        $now,
                        true,
                    );
                }
            }

            $attempts = QuestionAttempt::query()
                ->where('session_id', $currentSession->getKey())
                ->whereIn('question_id', $questionIds)
                ->get(['selected_option_ids', 'is_correct']);

            $currentSession->forceFill([
                'status' => SessionStatus::Completed,
                'paused_state' => null,
                'total' => count($questionIds),
                'answered_count' => $attempts
                    ->filter(fn (QuestionAttempt $attempt): bool => ($attempt->selected_option_ids ?? []) !== [])
                    ->count(),
                'correct_count' => $attempts->where('is_correct', true)->count(),
            ])->save();

            return [$currentSession, true];
        });

        [$completedSession, $changed] = $result;

        if ($changed) {
            event(new QuestionSessionProgressed(
                userId: (int) $completedSession->user_id,
                sessionId: (string) $completedSession->getKey(),
                completed: true,
            ));

            $actor = $completedSession->user()->first();
            Auditor::record(
                $completedSession->mode === SessionMode::Exam
                    ? AuditAction::ExamCompleted
                    : AuditAction::LearningSessionCompleted,
                $actor instanceof User ? $actor : null,
                $completedSession,
                ['status' => SessionStatus::Active->value],
                [
                    'status' => SessionStatus::Completed->value,
                    'total' => $completedSession->total,
                    'answered_count' => $completedSession->answered_count,
                    'correct_count' => $completedSession->correct_count,
                ],
                metadata: [
                    'question_session_id' => $completedSession->getKey(),
                    'exam_id' => $completedSession->exam_id,
                ],
                context: new AuditContext(sessionId: (string) $completedSession->getKey()),
            );
        }

        return $completedSession;
    }

    /**
     * @return array<int, int>
     */
    private function selectedOptionIds(?QuestionAttempt $attempt): array
    {
        if (! $attempt instanceof QuestionAttempt) {
            return [];
        }

        return array_values(array_unique(array_map(
            'intval',
            $attempt->selected_option_ids ?? [],
        )));
    }

    private function syncQuestionStatus(
        int $userId,
        Question $question,
        UserQuestionStatus $nextStatus,
        Carbon $attemptedAt,
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
            $attemptsCount = 1;
        }

        $status->fill([
            'status' => $nextStatus,
            'attempts_count' => $attemptsCount,
            'last_attempt_at' => $attemptedAt,
            'last_correct_at' => $nextStatus === UserQuestionStatus::Correct
                ? $attemptedAt
                : $status->last_correct_at,
        ])->save();
    }

    private function liveQuestionExists(Question $question): bool
    {
        return Question::withTrashed()->whereKey($question->getKey())->exists();
    }
}
