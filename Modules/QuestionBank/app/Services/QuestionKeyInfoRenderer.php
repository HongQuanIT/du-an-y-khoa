<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Services;

/** Resolve and safely render the key clinical phrases in a question stem. */
final class QuestionKeyInfoRenderer
{
    /**
     * Prefer editor-curated phrases and derive a conservative fallback for
     * legacy/demo questions that do not have key_info yet.
     *
     * @param  array<int, mixed>  $curated
     * @return list<string>
     */
    public function resolvePhrases(string $stem, array $curated): array
    {
        $curated = $this->normalizePhrases($curated);

        if ($curated !== []) {
            return $curated;
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', trim($stem)) ?: [];
        $candidates = collect($sentences)
            ->map(fn (string $sentence, int $index): array => [
                'index' => $index,
                'text' => trim($sentence),
            ])
            ->filter(function (array $item): bool {
                $text = $item['text'];

                return mb_strlen($text) >= 12
                    && mb_strlen($text) <= 240
                    && ! str_starts_with($text, '[')
                    && ! str_ends_with($text, '?');
            })
            ->map(function (array $item): array {
                $text = $item['text'];
                $clinicalSignals = preg_match_all(
                    '/\b(?:sốt|đau|đỏ|dịch|máu|ho|khó thở|mạch|huyết áp|nhiệt độ|xét nghiệm|creatinine|protein|casts?|fever|pain|blood|sputum|urine|biopsy|deposits?)\b/ui',
                    $text,
                );

                return $item + [
                    'score' => ($clinicalSignals ?: 0) * 4
                        + substr_count($text, ',') * 2
                        + (preg_match('/\d/u', $text) === 1 ? 2 : 0),
                ];
            })
            ->filter(fn (array $item): bool => $item['score'] > 0)
            ->sortByDesc('score')
            ->take(3)
            ->sortBy('index')
            ->pluck('text')
            ->values()
            ->all();

        if ($candidates !== []) {
            return $candidates;
        }

        $fallback = preg_replace('/^\[[^\]]+\]\s*/u', '', trim($stem)) ?? trim($stem);
        $fallback = trim($fallback, " \t\n\r\0\x0B?:");

        return mb_strlen($fallback) >= 4 ? [$fallback] : [];
    }

    /**
     * @param  array<int, mixed>  $phrases
     */
    public function render(string $stem, array $phrases): string
    {
        $phrases = collect($this->normalizePhrases($phrases))
            ->sortByDesc(fn (string $phrase): int => mb_strlen($phrase))
            ->values();
        /** @var list<array{start: int, end: int}> $matches */
        $matches = [];

        foreach ($phrases as $phrase) {
            $offset = 0;
            $length = mb_strlen($phrase);

            while (($position = mb_stripos($stem, $phrase, $offset)) !== false) {
                $end = $position + $length;
                $overlaps = collect($matches)->contains(
                    fn (array $match): bool => $position < $match['end'] && $end > $match['start'],
                );

                if (! $overlaps) {
                    $matches[] = ['start' => $position, 'end' => $end];
                }

                $offset = $end;
            }
        }

        usort($matches, fn (array $left, array $right): int => $left['start'] <=> $right['start']);

        if ($matches === []) {
            return $this->escape($stem);
        }

        $html = '';
        $offset = 0;

        foreach ($matches as $match) {
            $html .= $this->escape(mb_substr($stem, $offset, $match['start'] - $offset));
            $html .= '<span data-key-info class="underline decoration-amber-600 decoration-2 underline-offset-2">'
                .$this->escape(mb_substr($stem, $match['start'], $match['end'] - $match['start']))
                .'</span>';
            $offset = $match['end'];
        }

        return $html.$this->escape(mb_substr($stem, $offset));
    }

    /**
     * @param  array<int, mixed>  $phrases
     * @return list<string>
     */
    private function normalizePhrases(array $phrases): array
    {
        return collect($phrases)
            ->filter(fn (mixed $phrase): bool => is_string($phrase) && trim($phrase) !== '')
            ->map(fn (string $phrase): string => trim($phrase))
            ->unique()
            ->values()
            ->all();
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
