<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Collection;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionSimilarityMatch;
use Modules\QuestionBank\Services\QuestionContentFingerprint;
use Modules\QuestionBank\Services\QuestionSimilarityScorer;

final class FindSimilarQuestionsAction
{
    use AsAction;

    public function __construct(
        private readonly QuestionContentFingerprint $fingerprint,
        private readonly QuestionSimilarityScorer $scorer,
        private readonly ScanQuestionDuplicatesAction $scan,
    ) {}

    /**
     * Refresh persisted matches for one question against candidate peers.
     *
     * @return Collection<int, QuestionSimilarityMatch>
     */
    public function refreshFor(Question $question): Collection
    {
        if (! $question->relationLoaded('options')) {
            $question->load('options');
        }

        $this->fingerprint->persist($question);

        QuestionSimilarityMatch::query()
            ->where(function ($q) use ($question): void {
                $q->where('question_id_low', $question->getKey())
                    ->orWhere('question_id_high', $question->getKey());
            })
            ->delete();

        $candidates = $this->candidatesFor($question);

        foreach ($candidates as $candidate) {
            $result = $this->scorer->score($question, $candidate);
            if ($result['severity'] === null) {
                continue;
            }

            [$low, $high] = QuestionSimilarityMatch::orderedIds(
                (string) $question->getKey(),
                (string) $candidate->getKey(),
            );

            $this->scan->upsertPair(
                $low,
                $high,
                $result['percent'],
                $result['severity'],
                [
                    'stem_score' => $result['stem_score'],
                    'options_score' => $result['options_score'],
                    'exact' => $result['exact'],
                ],
            );
        }

        $question->forceFill(['similarity_checked_at' => now()])->saveQuietly();

        return $this->matchesFor($question);
    }

    /**
     * @return Collection<int, QuestionSimilarityMatch>
     */
    public function matchesFor(Question $question, int $limit = 20): Collection
    {
        return QuestionSimilarityMatch::query()
            ->where(function ($q) use ($question): void {
                $q->where('question_id_low', $question->getKey())
                    ->orWhere('question_id_high', $question->getKey());
            })
            ->with([
                'questionLow:id,code,stem,status,difficulty',
                'questionHigh:id,code,stem,status,difficulty',
            ])
            ->orderByDesc('score')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Question>
     */
    private function candidatesFor(Question $question): Collection
    {
        $id = (string) $question->getKey();
        $candidates = collect();

        if (filled($question->content_fingerprint)) {
            $exact = Question::query()
                ->with('options')
                ->where('content_fingerprint', $question->content_fingerprint)
                ->whereKeyNot($id)
                ->get();
            $candidates = $candidates->merge($exact);
        }

        $bucket = $this->fingerprint->stemBucket((string) $question->stem);
        $prefix = mb_substr($bucket, 0, 24);

        Question::query()
            ->with('options')
            ->whereKeyNot($id)
            ->orderBy('id')
            ->chunkById(200, function (Collection $peers) use ($question, $prefix, $candidates): void {
                foreach ($peers as $peer) {
                    /** @var Question $peer */
                    if ($question->content_fingerprint
                        && $peer->content_fingerprint === $question->content_fingerprint) {
                        $candidates->push($peer);

                        continue;
                    }

                    if ($prefix === '') {
                        continue;
                    }

                    $peerBucket = mb_substr($this->fingerprint->stemBucket((string) $peer->stem), 0, 24);
                    if ($peerBucket === $prefix || $this->tokenOverlapHint($question, $peer)) {
                        $candidates->push($peer);
                    }
                }
            });

        return $candidates->unique(fn (Question $q) => $q->getKey())->values();
    }

    private function tokenOverlapHint(Question $a, Question $b): bool
    {
        $tokensA = $this->fingerprint->tokens((string) $a->stem);
        $tokensB = $this->fingerprint->tokens((string) $b->stem);

        if ($tokensA === [] || $tokensB === []) {
            return false;
        }

        $overlap = count(array_intersect($tokensA, $tokensB));
        $minSize = min(count($tokensA), count($tokensB));

        return $minSize > 0 && ($overlap / $minSize) >= 0.25;
    }
}
