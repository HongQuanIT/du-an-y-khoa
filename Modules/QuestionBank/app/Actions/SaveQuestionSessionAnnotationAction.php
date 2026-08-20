<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Actions;

use App\Support\Concerns\AsAction;
use App\Support\Html\SafeHtml;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;

/** Persist sanitized per-question notes, stem highlights, key-info use and review flags. */
final class SaveQuestionSessionAnnotationAction
{
    use AsAction;

    /** @var list<string> */
    private const HIGHLIGHT_COLORS = ['#EF4444', '#F59E0B', '#10B981'];

    /**
     * @return array{note: string, note_html: string, stem_html: string, flagged: bool, key_info_used: bool, attending_tip_used: bool}
     */
    public function handle(
        QuestionSession $session,
        Question $question,
        ?string $note = null,
        ?string $noteHtml = null,
        ?string $stemHtml = null,
        ?bool $flagged = null,
        ?bool $keyInfoUsed = null,
        ?bool $attendingTipUsed = null,
    ): array {
        return DB::transaction(function () use (
            $session,
            $question,
            $note,
            $noteHtml,
            $stemHtml,
            $flagged,
            $keyInfoUsed,
            $attendingTipUsed,
        ): array {
            $currentSession = QuestionSession::query()
                ->lockForUpdate()
                ->findOrFail($session->getKey());
            $this->assertQuestionBelongsToSession($currentSession, $question);

            $key = (string) $question->getKey();
            /** @var array<string, array{note?: string, stem_html?: string, flagged?: bool, key_info_used?: bool, attending_tip_used?: bool}> $annotations */
            $annotations = $currentSession->annotations ?? [];
            $current = $annotations[$key] ?? [];

            if ($note !== null) {
                $current['note'] = Str::limit(trim(strip_tags($note)), 5000, '');
            }

            if ($noteHtml !== null) {
                $current['note_html'] = $this->sanitizeNoteHtml($noteHtml);
                $current['note'] = Str::limit(SafeHtml::plainText($current['note_html']), 5000, '');
            }

            if ($stemHtml !== null) {
                $current['stem_html'] = $this->sanitizeStemHtml($stemHtml, (string) $question->stem);
            }

            if ($flagged !== null) {
                $current['flagged'] = $flagged;
            }

            if ($keyInfoUsed === true) {
                // Once revealed, it remains part of this attempt's learning history.
                $current['key_info_used'] = true;
            }

            if ($attendingTipUsed === true) {
                // Once revealed, it remains part of this attempt's learning history.
                $current['attending_tip_used'] = true;
            }

            $annotations[$key] = [
                'note' => (string) ($current['note'] ?? ''),
                'note_html' => (string) ($current['note_html'] ?? nl2br(e((string) ($current['note'] ?? '')))),
                'stem_html' => (string) ($current['stem_html'] ?? SafeHtml::forDisplay((string) $question->stem)),
                'flagged' => (bool) ($current['flagged'] ?? false),
                'key_info_used' => (bool) ($current['key_info_used'] ?? false),
                'attending_tip_used' => (bool) ($current['attending_tip_used'] ?? false),
            ];

            $currentSession->forceFill(['annotations' => $annotations])->save();

            if ($flagged !== null) {
                QuestionAttempt::query()
                    ->where('session_id', $currentSession->getKey())
                    ->where('question_id', $question->getKey())
                    ->update(['flagged' => $flagged]);
            }

            return $annotations[$key];
        });
    }

    private function assertQuestionBelongsToSession(QuestionSession $session, Question $question): void
    {
        $questionIds = array_map('strval', $session->question_ids ?? []);

        if (! in_array((string) $question->getKey(), $questionIds, true)) {
            throw new InvalidArgumentException('Câu hỏi không thuộc phiên làm bài này.');
        }
    }

    /**
     * Keep highlight <mark> plus safe rich-text tags from the question stem.
     * Reject payloads that alter visible stem text.
     */
    private function sanitizeStemHtml(string $html, string $storedStem): string
    {
        $allowedTags = '<mark><p><br><strong><b><em><i><u><s><ul><ol><li><h2><h3><blockquote><a><img><sub><sup><span>';
        $allowed = strip_tags($html, $allowedTags);
        $allowed = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $allowed) ?? $allowed;
        $allowed = preg_replace('/javascript\s*:/i', '', $allowed) ?? $allowed;
        $allowed = preg_replace_callback(
            '/<mark\b([^>]*)>/i',
            function (array $matches): string {
                $attrs = $matches[1];
                $hex = $this->resolveHighlightHex($attrs);

                if ($hex === null) {
                    return '<mark class="rounded-sm">';
                }

                return '<mark class="rounded-sm" data-hl="'.$hex.'" style="background-color: '.$hex.'4D">';
            },
            $allowed,
        ) ?? $allowed;

        $normalize = static fn (string $value): string => preg_replace('/\s+/u', '', $value) ?? '';

        if ($normalize(SafeHtml::plainText($allowed)) !== $normalize(SafeHtml::plainText($storedStem))) {
            return SafeHtml::forDisplay($storedStem);
        }

        return $allowed;
    }

    private function sanitizeNoteHtml(string $html): string
    {
        $allowedTags = '<p><br><strong><b><em><i><u><s><ul><ol><li><h3><blockquote><mark><a>';
        $html = preg_replace('/<\s*(script|style)\b[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $html) ?? $html;
        $allowed = strip_tags($html, $allowedTags);
        $allowed = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $allowed) ?? $allowed;
        $allowed = preg_replace('/javascript\s*:/i', '', $allowed) ?? $allowed;
        $allowed = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $allowed) ?? $allowed;
        $allowed = preg_replace('/<a\b(?![^>]*\shref=)([^>]*)>/i', '<a$1>', $allowed) ?? $allowed;
        $allowed = preg_replace_callback('/<a\b([^>]*)>/i', function (array $matches): string {
            $attrs = $matches[1];
            if (preg_match('/href\s*=\s*["\']([^"\']+)["\']/i', $attrs, $href) !== 1) {
                return '<a>';
            }

            $url = $href[1];
            if (! str_starts_with($url, 'https://') && ! str_starts_with($url, 'http://') && ! str_starts_with($url, 'mailto:')) {
                return '<a>';
            }

            return '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer">';
        }, $allowed) ?? $allowed;

        return Str::limit(trim($allowed), 20000, '');
    }

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
