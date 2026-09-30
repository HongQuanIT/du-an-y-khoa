<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Services;

use App\Support\Html\SafeHtml;

/** Resolve and safely render authored hint phrases in a question stem. */
final class QuestionKeyInfoRenderer
{
    /**
     * Return only editor-authored hint phrases. Matching against the stem
     * happens in render(); questions with no phrases stay unmarked.
     *
     * @param  array<int, mixed>  $curated
     * @return list<string>
     */
    public function resolvePhrases(string $stem, array $curated): array
    {
        return $this->normalizePhrases($curated);
    }

    /**
     * Render the stem with key phrases highlighted.
     * Handles both plain-text legacy stems and HTML stems from the rich editor.
     *
     * @param  array<int, mixed>  $phrases
     */
    public function render(string $stem, array $phrases): string
    {
        $phrases = collect($this->normalizePhrases($phrases))
            ->sortByDesc(fn (string $phrase): int => mb_strlen($phrase))
            ->values();

        // If the stem looks like HTML (rich editor output), we must only
        // highlight inside text nodes — never inside tag attributes or tag names.
        if (SafeHtml::looksLikeHtml($stem)) {
            return $this->renderHtmlStem($stem, $phrases->all());
        }

        // Legacy plain-text stem — original character-offset highlighting.
        return $this->renderPlainStem($stem, $phrases->all());
    }

    // ── private ──────────────────────────────────────────────────────────────

    /**
     * Highlight key phrases inside an HTML stem without corrupting the markup.
     * Strategy: split the HTML into "text node" segments and "tag/entity" segments,
     * escape and highlight only text segments, then reassemble.
     *
     * @param  list<string>  $phrases
     */
    private function renderHtmlStem(string $stem, array $phrases): string
    {
        // First sanitize so we start from clean HTML
        $clean = SafeHtml::forDisplay($stem);

        if ($phrases === []) {
            return $clean;
        }

        // Split into tag tokens and text tokens
        $parts = preg_split('/(<[^>]+>)/u', $clean, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return $clean;
        }

        $result = '';
        foreach ($parts as $part) {
            if (str_starts_with($part, '<')) {
                // HTML tag — output verbatim
                $result .= $part;
            } else {
                // Plain-text node — decode entities, highlight, re-escape
                $text = html_entity_decode($part, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $result .= $this->highlightInPlainText($text, $phrases);
            }
        }

        return $result;
    }

    /**
     * Original approach for plain-text stems (no HTML tags).
     *
     * @param  list<string>  $phrases
     */
    private function renderPlainStem(string $stem, array $phrases): string
    {
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
     * Highlight occurrences of $phrases in a plain-text string, returning HTML.
     *
     * @param  list<string>  $phrases
     */
    private function highlightInPlainText(string $text, array $phrases): string
    {
        /** @var list<array{start: int, end: int}> $matches */
        $matches = [];

        foreach ($phrases as $phrase) {
            $offset = 0;
            $length = mb_strlen($phrase);

            while (($position = mb_stripos($text, $phrase, $offset)) !== false) {
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

        if ($matches === []) {
            return $this->escape($text);
        }

        usort($matches, fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        $html = '';
        $offset = 0;

        foreach ($matches as $match) {
            $html .= $this->escape(mb_substr($text, $offset, $match['start'] - $offset));
            $html .= '<span data-key-info class="underline decoration-amber-600 decoration-2 underline-offset-2">'
                .$this->escape(mb_substr($text, $match['start'], $match['end'] - $match['start']))
                .'</span>';
            $offset = $match['end'];
        }

        return $html.$this->escape(mb_substr($text, $offset));
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
