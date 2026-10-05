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
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Services\QuestionGrader;
use Modules\QuestionBank\Services\QuestionSessionSnapshots;
use Modules\QuestionBank\Support\AdaptiveTrace;
use Modules\QuestionBank\Support\SyncUserQuestionLearningState;
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
        private readonly SyncUserQuestionLearningState $learningState,
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
            $filters = is_array($currentSession->filters) ? $currentSession->filters : [];
            /** @var array<string, array<string, mixed>> $gradeReports */
            $gradeReports = is_array($filters['adaptive_grades'] ?? null) ? $filters['adaptive_grades'] : [];

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

                    $shouldSyncLearning = $currentSession->mode === SessionMode::Exam || ! $wasGraded;
                    if ($shouldSyncLearning && $this->liveQuestionExists($question)) {
                        $gradeReports[$questionId] = $this->learningState->applyGraded(
                            (int) $currentSession->user_id,
                            $question,
                            (bool) $attempt->is_correct,
                            $attempt->answered_at ?? $now,
                            (int) ($attempt->time_spent_seconds ?? 60),
                            true,
                        );
                    }

                    continue;
                }

                if ($attempt instanceof QuestionAttempt && $attempt->is_correct !== null) {
                    $attempt->forceFill(['is_correct' => null])->save();
                }

                if ($this->liveQuestionExists($question)) {
                    $gradeReports[$questionId] = $this->learningState->applyOmitted(
                        (int) $currentSession->user_id,
                        $question,
                        $now,
                    );
                }
            }

            $attempts = QuestionAttempt::query()
                ->where('session_id', $currentSession->getKey())
                ->whereIn('question_id', $questionIds)
                ->get(['question_id', 'selected_option_ids', 'is_correct', 'time_spent_seconds']);

            $filters['adaptive_grades'] = $gradeReports;

            $currentSession->forceFill([
                'status' => SessionStatus::Completed,
                'paused_state' => null,
                'filters' => $filters,
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

            $this->logAdaptiveGradedTable($completedSession);
        }

        return $completedSession;
    }

    private function logAdaptiveGradedTable(QuestionSession $session): void
    {
        if ($session->source !== SessionSource::WeakTopics) {
            return;
        }

        $filters = is_array($session->filters) ? $session->filters : [];
        $traceId = isset($filters['adaptive_trace_id']) ? (string) $filters['adaptive_trace_id'] : '';
        if ($traceId === '') {
            return;
        }

        /** @var array<string, array<string, mixed>> $grades */
        $grades = is_array($filters['adaptive_grades'] ?? null) ? $filters['adaptive_grades'] : [];
        $questionIds = array_values(array_unique(array_map('strval', $session->question_ids ?? [])));
        $items = [];
        foreach ($questionIds as $index => $questionId) {
            $row = $grades[$questionId] ?? null;
            if (! is_array($row)) {
                $items[] = [
                    'position' => $index + 1,
                    'question_id' => $questionId,
                    'result' => 'unknown',
                    'valid' => null,
                    's_before' => null,
                    's_after' => null,
                    't_days' => null,
                    'was_due' => null,
                    'weakness_after' => null,
                    'wrong_streak' => null,
                    'due_at' => null,
                    'time_spent_seconds' => null,
                    'note' => 'Không có báo cáo chấm',
                ];

                continue;
            }

            $items[] = [
                'position' => $index + 1,
                'question_id' => $questionId,
                'result' => (string) ($row['result'] ?? 'unknown'),
                'valid' => $row['valid'] ?? null,
                'learning_updated' => $row['learning_updated'] ?? null,
                's_before' => $row['s_before'] ?? null,
                's_after' => $row['s_after'] ?? null,
                't_days' => $row['t_days'] ?? null,
                'was_due' => $row['was_due'] ?? null,
                'weakness_after' => $row['weakness_after'] ?? null,
                'wrong_streak' => $row['wrong_streak'] ?? null,
                'recent_results' => $row['recent_results'] ?? [],
                'due_at' => $row['due_at'] ?? null,
                'thrash_blocked_until' => $row['thrash_blocked_until'] ?? null,
                'time_spent_seconds' => $row['time_spent_seconds'] ?? null,
                'version_reset' => $row['version_reset'] ?? false,
                'note' => $row['note'] ?? null,
            ];
        }

        $correct = count(array_filter($items, static fn (array $i): bool => ($i['result'] ?? '') === 'correct'));
        $incorrect = count(array_filter($items, static fn (array $i): bool => ($i['result'] ?? '') === 'incorrect'));
        $omitted = count(array_filter($items, static fn (array $i): bool => ($i['result'] ?? '') === 'omitted'));

        AdaptiveTrace::writeWithTrace($traceId, 'graded', [
            'session_id' => (string) $session->getKey(),
            'user_id' => (int) $session->user_id,
            'total' => count($items),
            'correct_count' => $correct,
            'incorrect_count' => $incorrect,
            'omitted_count' => $omitted,
            'items' => $items,
        ]);
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

    private function liveQuestionExists(Question $question): bool
    {
        return Question::withTrashed()->whereKey($question->getKey())->exists();
    }
}
