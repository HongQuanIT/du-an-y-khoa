<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\QuestionBank\Models\Question;
use Modules\Search\Support\SearchText;

/**
 * Normalize question content and build a stable SHA-256 fingerprint for exact-dup detection.
 */
final class QuestionContentFingerprint
{
    /**
     * @param  Collection<int, object{content?: string, is_correct?: bool}|array{content?: string, is_correct?: bool}>|null  $options
     */
    public function fingerprint(Question $question, ?Collection $options = null): string
    {
        return hash('sha256', $this->canonicalPayload($question, $options));
    }

    /**
     * @param  Collection<int, object{content?: string, is_correct?: bool}|array{content?: string, is_correct?: bool}>|null  $options
     */
    public function canonicalPayload(Question $question, ?Collection $options = null): string
    {
        $options ??= $question->relationLoaded('options')
            ? $question->options
            : $question->options()->get();

        $optionLines = $options
            ->map(function (mixed $option): string {
                $content = is_array($option)
                    ? (string) ($option['content'] ?? '')
                    : (string) ($option->content ?? '');
                $isCorrect = is_array($option)
                    ? (bool) ($option['is_correct'] ?? false)
                    : (bool) ($option->is_correct ?? false);

                return $this->normalize($content).'|'.($isCorrect ? '1' : '0');
            })
            ->sort()
            ->values()
            ->all();

        return implode("\n", [
            'stem:'.$this->normalize((string) $question->stem),
            'options:'.implode(';', $optionLines),
        ]);
    }

    public function normalize(string $htmlOrText): string
    {
        $plain = SearchText::plain($htmlOrText);
        $plain = mb_strtolower($plain, 'UTF-8');
        $plain = Str::ascii($plain);
        $plain = preg_replace('/[^a-z0-9\s]+/u', ' ', $plain) ?? $plain;

        return Str::squish($plain);
    }

    /** First N chars of normalized stem — used as near-dup candidate bucket. */
    public function stemBucket(string $stem, int $length = 48): string
    {
        $normalized = $this->normalize($stem);

        if ($normalized === '') {
            return '';
        }

        return mb_substr($normalized, 0, $length);
    }

    /**
     * @return list<string>
     */
    public function tokens(string $htmlOrText): array
    {
        $normalized = $this->normalize($htmlOrText);
        if ($normalized === '') {
            return [];
        }

        return array_values(array_filter(
            explode(' ', $normalized),
            fn (string $token): bool => mb_strlen($token) >= 2,
        ));
    }

    public function persist(Question $question): string
    {
        if (! $question->relationLoaded('options')) {
            $question->load('options');
        }

        $fingerprint = $this->fingerprint($question);
        $question->forceFill([
            'content_fingerprint' => $fingerprint,
        ])->saveQuietly();

        return $fingerprint;
    }
}
