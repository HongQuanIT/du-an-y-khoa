<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\QuestionStatus;

/**
 * Priority scoring + spaced repetition + mastery + light randomisation.
 */
final class AdaptiveQuestionSelector
{
    private const COVERAGE_RATIO = 0.60;

    private const REVIEW_RATIO = 0.25;

    public function __construct(
        private readonly QuestionContentFingerprint $fingerprints,
    ) {}

    /**
     * @param  Builder<Question>  $query
     * @return list<string>
     */
    public function select(int $userId, Builder $query, int $limit, bool $respectSchedule = true): array
    {
        if ($limit <= 0) {
            return [];
        }

        $rows = $this->rankedRows($userId, $query, $respectSchedule);
        if ($rows->isEmpty()) {
            return [];
        }

        $limit = min($limit, $rows->count());
        $coverageTarget = min($limit, (int) round($limit * self::COVERAGE_RATIO));
        $reviewTarget = min(
            $limit - $coverageTarget,
            (int) round($limit * self::REVIEW_RATIO),
        );
        $retentionTarget = $limit - $coverageTarget - $reviewTarget;

        $ready = $rows->where('cooling_down', false);
        $picked = collect()
            ->concat($ready->where('bucket', 'coverage')->take($coverageTarget))
            ->concat($ready->where('bucket', 'review')->take($reviewTarget))
            ->concat($ready->where('bucket', 'retention')->take($retentionTarget));

        // Empty quotas flow into the other ready buckets. Cooldown is a soft
        // boundary: it is only relaxed when needed to honour the requested
        // session size (for example selecting all 16 of 16 questions).
        $pickedIds = $picked->pluck('id')->all();
        $picked = $picked
            ->concat($ready->whereNotIn('id', $pickedIds))
            ->concat($rows->where('cooling_down', true))
            ->unique('id')
            ->take($limit)
            ->values();

        return $picked->pluck('id')->all();
    }

    /** @param Builder<Question> $query */
    public function eligibleCount(int $userId, Builder $query, bool $respectSchedule = true): int
    {
        return $this->rankedRows($userId, $query, $respectSchedule)->count();
    }

    /**
     * @param  Builder<Question>  $query
     * @return Collection<int, array{id: string, score: int, bucket: string, cooling_down: bool}>
     */
    private function rankedRows(int $userId, Builder $query, bool $respectSchedule): Collection
    {
        $questions = (clone $query)
            ->with([
                'tags:id,slug',
                'options:id,question_id,content,is_correct',
            ])
            ->get(['id', 'stem', 'exam_flag'])
            ->unique('id')
            // A question can have been imported twice under separate IDs.
            // Do not present the same clinical item twice in one learner pool.
            ->unique(fn (Question $question): string => $this->fingerprints->fingerprint($question))
            ->values();
        $questionIds = $questions
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id);

        if ($questionIds->isEmpty()) {
            return collect();
        }

        $states = QuestionStatus::query()
            ->where('user_id', $userId)
            ->whereIn('question_id', $questionIds->all())
            ->get()
            ->keyBy(fn (QuestionStatus $state): string => (string) $state->question_id);
        $latestAttempts = QuestionAttempt::query()
            ->where('user_id', $userId)
            ->whereIn('question_id', $questionIds->all())
            ->orderByDesc('answered_at')
            ->orderByDesc('id')
            ->get(['session_id', 'question_id', 'is_correct', 'used_hint', 'answered_at'])
            ->unique('question_id')
            ->keyBy(fn (QuestionAttempt $attempt): string => (string) $attempt->question_id);
        $recentSessionRanks = QuestionSession::query()
            ->where('user_id', $userId)
            ->where('status', SessionStatus::Completed)
            ->latest('updated_at')
            ->latest('id')
            ->limit(20)
            ->pluck('id')
            ->mapWithKeys(static fn ($id, int $rank): array => [(string) $id => $rank]);
        $now = Carbon::now();
        $ranked = [];

        foreach ($questions as $question) {
            $questionId = (string) $question->getKey();
            /** @var QuestionAttempt|null $attempt */
            $attempt = $latestAttempts->get($questionId);
            /** @var QuestionStatus|null $state */
            $state = $states->get($questionId);
            $score = $this->priorityScore($attempt, $state, $now, $respectSchedule);

            if ($score === null) {
                continue;
            }

            // A small jitter avoids a rigid, predictable order without
            // allowing low-priority questions to leapfrog whole score bands.
            $ranked[] = [
                'id' => $questionId,
                'score' => $score + $this->highYieldBonus($question) + random_int(-4, 4),
                'bucket' => $this->bucket($attempt, $state, $now),
                'cooling_down' => $this->isCoolingDown($attempt, $state, $recentSessionRanks),
            ];
        }

        usort($ranked, static fn (array $left, array $right): int => $right['score'] <=> $left['score']);

        return collect($ranked);
    }

    private function bucket(?QuestionAttempt $attempt, ?QuestionStatus $state, Carbon $now): string
    {
        if ($attempt === null) {
            return 'coverage';
        }

        if (
            $attempt->is_correct !== true
            || $attempt->used_hint
            || ($state?->next_review_at !== null && $state->next_review_at->lte($now))
        ) {
            return 'review';
        }

        return 'retention';
    }

    /** @param Collection<string, int> $recentSessionRanks */
    private function isCoolingDown(
        ?QuestionAttempt $attempt,
        ?QuestionStatus $state,
        Collection $recentSessionRanks,
    ): bool {
        if ($attempt === null) {
            return false;
        }

        $sessionRank = $recentSessionRanks->get((string) $attempt->session_id);
        if (! is_int($sessionRank)) {
            return false;
        }

        $cooldown = match (true) {
            $attempt->is_correct === false => 1,
            $attempt->used_hint => 1,
            (int) ($state?->correct_streak ?? 0) >= 4 => 6,
            (int) ($state?->correct_streak ?? 0) >= 2 => 4,
            default => 2,
        };

        return $sessionRank < $cooldown;
    }

    private function highYieldBonus(Question $question): int
    {
        if ($question->tags->contains('slug', 'high-yield')) {
            return 100;
        }

        return $question->exam_flag ? 80 : 0;
    }

    private function priorityScore(
        ?QuestionAttempt $attempt,
        ?QuestionStatus $state,
        Carbon $now,
        bool $respectSchedule,
    ): ?int {
        if ($attempt === null) {
            return 70;
        }

        $mastery = (int) ($state?->mastery_score ?? 0);
        $incorrectCount = (int) ($state?->incorrect_count ?? ($attempt->is_correct === false ? 1 : 0));
        $lastAttemptAt = $attempt->answered_at ?? $state?->last_attempt_at;

        if ($attempt->is_correct === false) {
            return 130
                + min(30, $incorrectCount * 5)
                - (int) round($mastery * 0.25)
                + $this->ageBonus($lastAttemptAt, $now);
        }

        if ($attempt->is_correct === null) {
            return 105 - (int) round($mastery * 0.20);
        }

        $nextReviewAt = $state?->next_review_at
            ?? $lastAttemptAt?->copy()->addDays($attempt->used_hint ? 1 : 3);

        if ($respectSchedule && $nextReviewAt !== null && $nextReviewAt->isFuture()) {
            return null;
        }

        $overdueDays = $nextReviewAt === null || $nextReviewAt->isFuture()
            ? 0
            : min(60, (int) $nextReviewAt->diffInDays($now));

        return 90
            + ($attempt->used_hint ? 20 : 0)
            + $overdueDays
            - (int) round($mastery * 0.45);
    }

    private function ageBonus(?Carbon $lastAttemptAt, Carbon $now): int
    {
        if ($lastAttemptAt === null) {
            return 0;
        }

        return min(20, (int) $lastAttemptAt->diffInDays($now));
    }
}
