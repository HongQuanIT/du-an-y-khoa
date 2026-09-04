<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Facades\Cache;
use Modules\QuestionBank\Enums\DuplicateSeverity;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionSimilarityMatch;
use Modules\QuestionBank\Services\QuestionContentFingerprint;
use Modules\QuestionBank\Services\QuestionSimilarityScorer;

final class ScanQuestionDuplicatesAction
{
    use AsAction;

    public const CACHE_LAST_SCANNED = 'questions.duplicates.last_scanned_at';

    public const CACHE_SCAN_STATUS = 'questions.duplicates.scan_status';

    public function __construct(
        private readonly QuestionContentFingerprint $fingerprint,
        private readonly QuestionSimilarityScorer $scorer,
    ) {}

    /**
     * Full-bank rebuild of similarity matches.
     *
     * @return array{questions: int, pairs: int}
     */
    public function handle(): array
    {
        Cache::put(self::CACHE_SCAN_STATUS, 'running', now()->addHour());

        try {
            $questions = Question::query()
                ->with(['options' => fn ($q) => $q->orderBy('order')])
                ->get();

            foreach ($questions as $question) {
                $this->fingerprint->persist($question);
            }

            QuestionSimilarityMatch::query()->delete();

            $pairs = 0;
            $byFingerprint = $questions->groupBy('content_fingerprint');

            foreach ($byFingerprint as $fp => $group) {
                if (! is_string($fp) || $fp === '' || $group->count() < 2) {
                    continue;
                }

                $ids = $group->pluck('id')->sort()->values()->all();
                for ($i = 0; $i < count($ids); $i++) {
                    for ($j = $i + 1; $j < count($ids); $j++) {
                        $this->upsertPair(
                            (string) $ids[$i],
                            (string) $ids[$j],
                            100.0,
                            DuplicateSeverity::Exact,
                            ['stem_score' => 100.0, 'options_score' => 100.0, 'exact' => true],
                        );
                        $pairs++;
                    }
                }
            }

            $buckets = [];
            foreach ($questions as $question) {
                $bucket = $this->fingerprint->stemBucket((string) $question->stem);
                if ($bucket === '') {
                    continue;
                }
                // Coarser bucket for near-dup recall when stems diverge after first words.
                $key = mb_substr($bucket, 0, 24);
                $buckets[$key][] = $question;
            }

            $seen = [];
            foreach ($buckets as $group) {
                $count = count($group);
                if ($count < 2) {
                    continue;
                }

                for ($i = 0; $i < $count; $i++) {
                    for ($j = $i + 1; $j < $count; $j++) {
                        /** @var Question $left */
                        $left = $group[$i];
                        /** @var Question $right */
                        $right = $group[$j];
                        [$low, $high] = QuestionSimilarityMatch::orderedIds(
                            (string) $left->getKey(),
                            (string) $right->getKey(),
                        );
                        $pairKey = $low.'|'.$high;
                        if (isset($seen[$pairKey])) {
                            continue;
                        }
                        $seen[$pairKey] = true;

                        if ($left->content_fingerprint
                            && $left->content_fingerprint === $right->content_fingerprint) {
                            continue;
                        }

                        $result = $this->scorer->score($left, $right);
                        if ($result['severity'] === null) {
                            continue;
                        }

                        $this->upsertPair(
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
                        $pairs++;
                    }
                }
            }

            $now = now();
            Question::query()->update(['similarity_checked_at' => $now]);
            Cache::put(self::CACHE_LAST_SCANNED, $now->toIso8601String(), now()->addYear());
            Cache::put(self::CACHE_SCAN_STATUS, 'ready', now()->addDay());

            return [
                'questions' => $questions->count(),
                'pairs' => $pairs,
            ];
        } catch (\Throwable $e) {
            Cache::put(self::CACHE_SCAN_STATUS, 'failed', now()->addHour());

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $signals
     */
    public function upsertPair(
        string $low,
        string $high,
        float $score,
        DuplicateSeverity $severity,
        array $signals,
    ): void {
        QuestionSimilarityMatch::query()->updateOrCreate(
            [
                'question_id_low' => $low,
                'question_id_high' => $high,
            ],
            [
                'score' => $score,
                'severity' => $severity->value,
                'signals' => $signals,
                'detected_at' => now(),
            ],
        );
    }
}
