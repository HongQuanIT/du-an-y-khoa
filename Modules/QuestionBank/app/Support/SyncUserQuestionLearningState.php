<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use Illuminate\Support\Carbon;
use Modules\QuestionBank\Enums\UserQuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionStatus as UserQuestionStatusModel;

/**
 * Cập nhật rollup học tập adaptive (đúng/sai đã chấm). Dùng chung Study + Exam complete.
 */
final class SyncUserQuestionLearningState
{
    /**
     * @return array<string, mixed> Báo cáo trước/sau để log kiểm tra chấm điểm.
     */
    public function applyGraded(
        int $userId,
        Question $question,
        bool $isCorrect,
        Carbon $answeredAt,
        int $timeSpentSeconds = 60,
        bool $incrementAttempts = true,
        bool $freezeStability = false,
    ): array {
        $status = UserQuestionStatusModel::firstOrNew([
            'user_id' => $userId,
            'question_id' => $question->getKey(),
        ]);

        $contentVersion = (int) ($question->published_version ?? 0);
        $versionMismatch = $status->exists
            && $status->content_version !== null
            && (int) $status->content_version !== $contentVersion;

        $sBefore = $versionMismatch || $status->memory_stability_days === null
            ? null
            : (float) $status->memory_stability_days;
        $lastGradedBefore = $versionMismatch ? null : $status->last_graded_at;
        $tDays = $lastGradedBefore !== null
            ? MemoryStability::elapsedDays($lastGradedBefore, $answeredAt)
            : null;
        $wasDue = $sBefore !== null && $lastGradedBefore !== null
            ? MemoryStability::isDue($sBefore, $lastGradedBefore, $answeredAt)
            : null;

        if ($versionMismatch) {
            $status->fill([
                'recent_results' => [],
                'wrong_streak' => 0,
                'thrash_blocked_until' => null,
                'memory_stability_days' => null,
                'last_graded_at' => null,
                'correct_count' => 0,
                'wrong_count' => 0,
                'attempts_count' => 0,
            ]);
            $sBefore = null;
            $lastGradedBefore = null;
            $tDays = null;
            $wasDue = null;
        }

        $valid = AdaptiveLearning::isValidGradedResponse($timeSpentSeconds);

        $attemptsCount = (int) ($status->attempts_count ?? 0);
        $correctCount = (int) ($status->correct_count ?? 0);
        $wrongCount = (int) ($status->wrong_count ?? 0);

        if ($incrementAttempts && $valid) {
            $attemptsCount++;
            if ($isCorrect) {
                $correctCount++;
            } else {
                $wrongCount++;
            }
        } elseif (! $status->exists && $valid) {
            $attemptsCount = 1;
            $correctCount = $isCorrect ? 1 : 0;
            $wrongCount = $isCorrect ? 0 : 1;
        }

        $answerStatus = $isCorrect ? UserQuestionStatus::Correct : UserQuestionStatus::Incorrect;
        $attributes = [
            'status' => $status->exists && $status->status === UserQuestionStatus::Marked
                ? UserQuestionStatus::Marked
                : $answerStatus,
            'attempts_count' => $attemptsCount,
            'correct_count' => $correctCount,
            'wrong_count' => $wrongCount,
            'last_attempt_at' => $answeredAt,
            'last_seen_at' => $answeredAt,
            'last_correct_at' => $isCorrect ? $answeredAt : $status->last_correct_at,
            'content_version' => $contentVersion > 0 ? $contentVersion : $status->content_version,
        ];

        if (! $valid) {
            $status->fill($attributes)->save();

            return $this->report(
                questionId: (string) $question->getKey(),
                result: $isCorrect ? 'correct' : 'incorrect',
                valid: false,
                learningUpdated: false,
                sBefore: $sBefore,
                sAfter: $status->memory_stability_days !== null ? (float) $status->memory_stability_days : $sBefore,
                tDays: $tDays,
                wasDue: $wasDue,
                weaknessAfter: AdaptiveLearning::weakness(
                    is_array($status->recent_results) ? $status->recent_results : [],
                ),
                wrongStreak: (int) ($status->wrong_streak ?? 0),
                recentResults: is_array($status->recent_results) ? $status->recent_results : [],
                dueAt: AdaptiveLearning::dueAt(
                    $status->memory_stability_days !== null ? (float) $status->memory_stability_days : $sBefore,
                    $status->last_graded_at ?? $lastGradedBefore,
                )?->toIso8601String(),
                thrashBlockedUntil: $status->thrash_blocked_until?->toIso8601String(),
                versionReset: $versionMismatch,
                timeSpentSeconds: $timeSpentSeconds,
                note: 'Lượt quá nhanh — không đổi W/S/streak',
            );
        }

        $sAfter = $sBefore;
        $recentAfter = is_array($status->recent_results) ? $status->recent_results : [];
        $streakAfter = (int) ($status->wrong_streak ?? 0);
        $thrashUntil = $status->thrash_blocked_until;
        $learningUpdated = false;

        if ($incrementAttempts || ! $status->exists || $versionMismatch) {
            $prevRecent = is_array($status->recent_results) ? $status->recent_results : [];
            $learning = AdaptiveLearning::afterGrade(
                $prevRecent,
                (int) ($status->wrong_streak ?? 0),
                $isCorrect,
                $answeredAt,
                $answeredAt,
            );

            $attributes['recent_results'] = $learning['recent_results'];
            $attributes['wrong_streak'] = $learning['wrong_streak'];
            $attributes['thrash_blocked_until'] = $isCorrect
                ? null
                : $learning['thrash_blocked_until'];
            $recentAfter = $learning['recent_results'];
            $streakAfter = $learning['wrong_streak'];
            $thrashUntil = $learning['thrash_blocked_until'];
            $learningUpdated = true;

            if ($freezeStability && $sBefore !== null && $lastGradedBefore !== null) {
                // Luyện thêm: cập nhật W/streak, giữ S và mốc last_graded_at.
                $sAfter = $sBefore;
            } else {
                $attributes['memory_stability_days'] = MemoryStability::afterGrade(
                    $sBefore,
                    $isCorrect,
                    $lastGradedBefore,
                    $answeredAt,
                );
                $attributes['last_graded_at'] = $answeredAt;
                $sAfter = (float) $attributes['memory_stability_days'];
            }
        }

        $status->fill($attributes)->save();

        $note = null;
        if ($learningUpdated && $freezeStability && $sBefore !== null) {
            $note = 'Luyện thêm — cập nhật W, giữ độ bền';
        } elseif ($learningUpdated && $sBefore === null) {
            $note = $isCorrect ? 'Lần đầu đúng → S = 3' : 'Lần đầu sai → S = 1';
        } elseif ($learningUpdated && ! $isCorrect) {
            $note = 'Sai → về bậc 1';
        } elseif ($learningUpdated && $wasDue === true) {
            $note = 'Đúng đúng hạn → lên bậc';
        } elseif ($learningUpdated && $wasDue === false) {
            $note = 'Đúng sớm → giữ bậc';
        }

        $dueAnchor = $freezeStability && $lastGradedBefore !== null
            ? $lastGradedBefore
            : ($learningUpdated && ! $freezeStability ? $answeredAt : ($status->last_graded_at ?? $lastGradedBefore));

        return $this->report(
            questionId: (string) $question->getKey(),
            result: $isCorrect ? 'correct' : 'incorrect',
            valid: true,
            learningUpdated: $learningUpdated,
            sBefore: $sBefore,
            sAfter: $sAfter,
            tDays: $tDays,
            wasDue: $wasDue,
            weaknessAfter: AdaptiveLearning::weakness(array_map(
                static fn ($v): bool => (bool) $v,
                $recentAfter,
            )),
            wrongStreak: $streakAfter,
            recentResults: array_map(static fn ($v): bool => (bool) $v, $recentAfter),
            dueAt: AdaptiveLearning::dueAt($sAfter, $dueAnchor)?->toIso8601String(),
            thrashBlockedUntil: $thrashUntil?->toIso8601String(),
            versionReset: $versionMismatch,
            timeSpentSeconds: $timeSpentSeconds,
            note: $note,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function applyOmitted(int $userId, Question $question, Carbon $attemptedAt): array
    {
        $status = UserQuestionStatusModel::firstOrNew([
            'user_id' => $userId,
            'question_id' => $question->getKey(),
        ]);

        $s = $status->memory_stability_days !== null ? (float) $status->memory_stability_days : null;

        $status->fill([
            'status' => UserQuestionStatus::Omitted,
            'omitted_count' => (int) ($status->omitted_count ?? 0) + 1,
            'attempts_count' => (int) ($status->attempts_count ?? 0) + 1,
            'last_attempt_at' => $attemptedAt,
            'last_seen_at' => $attemptedAt,
            'content_version' => (int) ($question->published_version ?? 0) ?: $status->content_version,
        ])->save();

        return $this->report(
            questionId: (string) $question->getKey(),
            result: 'omitted',
            valid: true,
            learningUpdated: false,
            sBefore: $s,
            sAfter: $s,
            tDays: null,
            wasDue: null,
            weaknessAfter: AdaptiveLearning::weakness(
                is_array($status->recent_results) ? array_map(static fn ($v): bool => (bool) $v, $status->recent_results) : [],
            ),
            wrongStreak: (int) ($status->wrong_streak ?? 0),
            recentResults: is_array($status->recent_results)
                ? array_map(static fn ($v): bool => (bool) $v, $status->recent_results)
                : [],
            dueAt: AdaptiveLearning::dueAt($s, $status->last_graded_at)?->toIso8601String(),
            thrashBlockedUntil: $status->thrash_blocked_until?->toIso8601String(),
            versionReset: false,
            timeSpentSeconds: null,
            note: 'Bỏ qua — không đổi S / last_graded_at',
        );
    }

    /**
     * @param  list<bool>  $recentResults
     * @return array<string, mixed>
     */
    private function report(
        string $questionId,
        string $result,
        bool $valid,
        bool $learningUpdated,
        ?float $sBefore,
        ?float $sAfter,
        ?float $tDays,
        ?bool $wasDue,
        ?float $weaknessAfter,
        int $wrongStreak,
        array $recentResults,
        ?string $dueAt,
        ?string $thrashBlockedUntil,
        bool $versionReset,
        ?int $timeSpentSeconds,
        ?string $note,
    ): array {
        return [
            'question_id' => $questionId,
            'result' => $result,
            'valid' => $valid,
            'learning_updated' => $learningUpdated,
            's_before' => $sBefore,
            's_after' => $sAfter,
            't_days' => $tDays,
            'was_due' => $wasDue,
            'weakness_after' => $weaknessAfter,
            'wrong_streak' => $wrongStreak,
            'recent_results' => $recentResults,
            'due_at' => $dueAt,
            'thrash_blocked_until' => $thrashBlockedUntil,
            'version_reset' => $versionReset,
            'time_spent_seconds' => $timeSpentSeconds,
            'note' => $note,
        ];
    }
}
