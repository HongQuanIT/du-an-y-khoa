<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Actions;

use Illuminate\Support\Str;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;

/**
 * Persist per-question notes / highlights / flag on the study session
 * so review can restore them after the task finishes.
 *
 * @return array{note: string, stem_html: string, flagged: bool}
 */
final class SavePlanSessionAnnotationAction
{
    /** @var list<string> */
    private const HIGHLIGHT_COLORS = ['#EF4444', '#F59E0B', '#10B981'];

    /**
     * @return array{note: string, stem_html: string, flagged: bool}
     */
    public function handle(
        QuestionSession $session,
        Question $question,
        ?string $note = null,
        ?string $stemHtml = null,
        ?bool $flagged = null,
    ): array {
        $key = (string) $question->getKey();
        /** @var array<string, array{note?: string, stem_html?: string, flagged?: bool}> $annotations */
        $annotations = $session->annotations ?? [];
        $current = $annotations[$key] ?? [];

        if ($note !== null) {
            $current['note'] = Str::limit(trim(strip_tags($note)), 5000, '');
        }

        if ($stemHtml !== null) {
            $current['stem_html'] = $this->sanitizeStemHtml($stemHtml, (string) $question->stem);
        }

        if ($flagged !== null) {
            $current['flagged'] = $flagged;
        }

        $annotations[$key] = [
            'note' => (string) ($current['note'] ?? ''),
            'stem_html' => (string) ($current['stem_html'] ?? e((string) $question->stem)),
            'flagged' => (bool) ($current['flagged'] ?? false),
        ];

        $session->forceFill(['annotations' => $annotations])->save();

        if ($flagged !== null) {
            QuestionAttempt::query()
                ->where('session_id', $session->getKey())
                ->where('question_id', $question->getKey())
                ->update(['flagged' => $flagged]);
        }

        return $annotations[$key];
    }

    /**
     * Keep only highlight <mark> tags; reject payloads that alter stem text.
     *
     * Browsers often serialize `style.backgroundColor` as `rgba(...)` via
     * innerHTML, so both hex and rgba forms must be preserved — otherwise
     * review falls back to the UA yellow <mark> colour.
     */
    private function sanitizeStemHtml(string $html, string $plainStem): string
    {
        $allowed = strip_tags($html, '<mark>');
        $allowed = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $allowed) ?? $allowed;
        $allowed = preg_replace_callback(
            '/<mark\b([^>]*)>/i',
            function (array $matches): string {
                $attrs = $matches[1] ?? '';
                $hex = $this->resolveHighlightHex($attrs);

                if ($hex === null) {
                    return '<mark class="rounded-sm">';
                }

                return '<mark class="rounded-sm" data-hl="'.$hex.'" style="background-color: '.$hex.'4D">';
            },
            $allowed,
        ) ?? $allowed;

        $text = html_entity_decode(strip_tags($allowed), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalize = static fn (string $value): string => preg_replace('/\s+/u', '', $value) ?? '';

        if ($normalize($text) !== $normalize($plainStem)) {
            return e($plainStem);
        }

        return $allowed;
    }

    /**
     * Resolve one of the allowed highlight colours from mark attributes.
     */
    private function resolveHighlightHex(string $attrs): ?string
    {
        if (preg_match('/data-hl\s*=\s*["\']?(#[0-9A-Fa-f]{6})["\']?/i', $attrs, $match) === 1) {
            return $this->normalizeAllowedHex($match[1]);
        }

        if (preg_match('/background-color\s*:\s*(#[0-9A-Fa-f]{6,8})/i', $attrs, $match) === 1) {
            return $this->normalizeAllowedHex(substr($match[1], 0, 7));
        }

        if (preg_match(
            '/background-color\s*:\s*rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*[\d.]+)?\s*\)/i',
            $attrs,
            $match,
        ) === 1) {
            $r = max(0, min(255, (int) $match[1]));
            $g = max(0, min(255, (int) $match[2]));
            $b = max(0, min(255, (int) $match[3]));

            return $this->normalizeAllowedHex(sprintf('#%02X%02X%02X', $r, $g, $b));
        }

        return null;
    }

    private function normalizeAllowedHex(string $hex): ?string
    {
        $hex = '#'.strtoupper(ltrim($hex, '#'));

        foreach (self::HIGHLIGHT_COLORS as $allowed) {
            if ($hex === $allowed) {
                return $allowed;
            }
        }

        // Map near-matches from rgba rounding (e.g. 0.3 alpha serialization).
        $best = null;
        $bestDistance = PHP_INT_MAX;
        $rgb = $this->hexToRgb($hex);

        if ($rgb === null) {
            return null;
        }

        foreach (self::HIGHLIGHT_COLORS as $allowed) {
            $candidate = $this->hexToRgb($allowed);
            if ($candidate === null) {
                continue;
            }

            $distance = abs($rgb[0] - $candidate[0])
                + abs($rgb[1] - $candidate[1])
                + abs($rgb[2] - $candidate[2]);

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $allowed;
            }
        }

        return $bestDistance <= 15 ? $best : null;
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private function hexToRgb(string $hex): ?array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) < 6) {
            return null;
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
