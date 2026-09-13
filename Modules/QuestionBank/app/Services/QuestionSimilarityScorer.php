<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Services;

use Illuminate\Support\Collection;
use Modules\QuestionBank\Enums\DuplicateSeverity;
use Modules\QuestionBank\Models\Question;

/**
 * Lexical similarity: stem ~70% + options bag ~30%.
 *
 * @phpstan-type ScoreResult array{
 *     percent: float,
 *     severity: DuplicateSeverity|null,
 *     stem_score: float,
 *     options_score: float,
 *     exact: bool
 * }
 */
final class QuestionSimilarityScorer
{
    private const STEM_WEIGHT = 0.70;

    private const OPTIONS_WEIGHT = 0.30;

    public function __construct(
        private readonly QuestionContentFingerprint $fingerprint,
    ) {}

    /**
     * @return ScoreResult
     */
    public function score(Question $a, Question $b): array
    {
        return $this->scoreFromParts(
            (string) $a->stem,
            ($a->relationLoaded('options') ? $a->options : $a->options()->get())->all(),
            (string) $b->stem,
            ($b->relationLoaded('options') ? $b->options : $b->options()->get())->all(),
            $a->content_fingerprint ?: $this->fingerprint->fingerprint($a),
            $b->content_fingerprint ?: $this->fingerprint->fingerprint($b),
        );
    }

    /**
     * @param  list<array{content?: string, is_correct?: bool}|object>  $optionsA
     * @param  list<array{content?: string, is_correct?: bool}|object>  $optionsB
     * @return ScoreResult
     */
    public function scoreFromParts(
        string $stemA,
        array $optionsA,
        string $stemB,
        array $optionsB,
        ?string $fingerprintA = null,
        ?string $fingerprintB = null,
    ): array {
        $fpA = $fingerprintA ?: $this->fingerprint->fingerprintFromParts(
            $stemA,
            $this->normalizeOptionList($optionsA),
        );
        $fpB = $fingerprintB ?: $this->fingerprint->fingerprintFromParts(
            $stemB,
            $this->normalizeOptionList($optionsB),
        );

        if ($fpA !== '' && $fpA === $fpB) {
            return [
                'percent' => 100.0,
                'severity' => DuplicateSeverity::Exact,
                'stem_score' => 100.0,
                'options_score' => 100.0,
                'exact' => true,
            ];
        }

        $stemScore = $this->textSimilarity(
            $this->fingerprint->normalize($stemA),
            $this->fingerprint->normalize($stemB),
        );

        $optionsScore = $this->optionsSimilarity(collect($optionsA), collect($optionsB));
        $percent = round(($stemScore * self::STEM_WEIGHT) + ($optionsScore * self::OPTIONS_WEIGHT), 2);

        return [
            'percent' => $percent,
            'severity' => DuplicateSeverity::fromPercent($percent),
            'stem_score' => round($stemScore, 2),
            'options_score' => round($optionsScore, 2),
            'exact' => false,
        ];
    }

    /**
     * @param  list<array{content?: string, is_correct?: bool}|object>  $options
     * @return list<array{content: string, is_correct: bool}>
     */
    private function normalizeOptionList(array $options): array
    {
        return array_values(array_map(function (mixed $option): array {
            if (is_array($option)) {
                return [
                    'content' => (string) ($option['content'] ?? ''),
                    'is_correct' => (bool) ($option['is_correct'] ?? false),
                ];
            }

            return [
                'content' => (string) ($option->content ?? ''),
                'is_correct' => (bool) ($option->is_correct ?? false),
            ];
        }, $options));
    }

    /**
     * @param  Collection<int, mixed>  $optionsA
     * @param  Collection<int, mixed>  $optionsB
     */
    private function optionsSimilarity(Collection $optionsA, Collection $optionsB): float
    {
        $tokensA = $this->optionTokens($optionsA);
        $tokensB = $this->optionTokens($optionsB);

        if ($tokensA === [] && $tokensB === []) {
            return 100.0;
        }

        if ($tokensA === [] || $tokensB === []) {
            return 0.0;
        }

        return $this->jaccard($tokensA, $tokensB);
    }

    /**
     * @param  Collection<int, mixed>  $options
     * @return list<string>
     */
    private function optionTokens(Collection $options): array
    {
        $tokens = [];

        foreach ($options as $option) {
            $content = is_array($option)
                ? (string) ($option['content'] ?? '')
                : (string) ($option->content ?? '');
            foreach ($this->fingerprint->tokens($content) as $token) {
                $tokens[] = $token;
            }
        }

        sort($tokens);

        return array_values(array_unique($tokens));
    }

    private function textSimilarity(string $a, string $b): float
    {
        if ($a === '' && $b === '') {
            return 100.0;
        }

        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 100.0;
        }

        $tokenScore = $this->jaccard(
            $this->fingerprint->tokens($a),
            $this->fingerprint->tokens($b),
        );

        similar_text($a, $b, $percent);

        // Blend token Jaccard with character-level similar_text for short medical stems.
        return ($tokenScore * 0.65) + (((float) $percent) * 0.35);
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private function jaccard(array $a, array $b): float
    {
        if ($a === [] && $b === []) {
            return 100.0;
        }

        $setA = array_unique($a);
        $setB = array_unique($b);
        $intersection = count(array_intersect($setA, $setB));
        $union = count(array_unique(array_merge($setA, $setB)));

        if ($union === 0) {
            return 0.0;
        }

        return ($intersection / $union) * 100.0;
    }
}
