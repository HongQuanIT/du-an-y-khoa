<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Actions;

use App\Support\Concerns\AsAction;
use App\Support\Html\SafeHtml;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\QuestionBank\Enums\UserQuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\QuestionStatus as UserQuestionStatusModel;

/** Persist sanitized per-question notes, stem highlights, key-info use and review flags. */
final class SaveQuestionSessionAnnotationAction
{
    use AsAction;

    /** @var list<string> */
    private const HIGHLIGHT_COLORS = ['#EF4444', '#F59E0B', '#10B981'];

    /**
     * @return array{note: string, stem_html: string, flagged: bool, key_info_used: bool, attending_tip_used: bool}
     */
    public function handle(
        QuestionSession $session,
        Question $question,
        ?string $note = null,
        ?string $stemHtml = null,
        ?bool $flagged = null,
        ?bool $keyInfoUsed = null,
        ?bool $attendingTipUsed = null,
    ): array {
        return DB::transaction(function () use (
            $session,
            $question,
            $note,
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

                if (Question::withTrashed()->whereKey($question->getKey())->exists()) {
                    $this->syncMarkedFallback((int) $currentSession->user_id, $question, $flagged);
                }
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
     * Until Bookmark persistence lands, a flagged question is represented by
     * `question_status=marked`. Unflagging restores the latest answer state.
     */
    private function syncMarkedFallback(int $userId, Question $question, bool $flagged): void
    {
        $status = UserQuestionStatusModel::firstOrNew([
            'user_id' => $userId,
            'question_id' => $question->getKey(),
        ]);

        if ($flagged) {
            $status->forceFill(['status' => UserQuestionStatus::Marked])->save();

            return;
        }

        if (! $status->exists || $status->status !== UserQuestionStatus::Marked) {
            return;
        }

        $latestAttempt = QuestionAttempt::query()
            ->where('user_id', $userId)
            ->where('question_id', $question->getKey())
            // Exam autosaves are deliberately ungraded and must not leak an
            // omitted/correctness state before explicit completion.
            ->whereNotNull('is_correct')
            ->orderByDesc('answered_at')
            ->orderByDesc('id')
            ->first();

        $restored = match (true) {
            $latestAttempt === null => UserQuestionStatus::Unseen,
            $latestAttempt->is_correct === true => UserQuestionStatus::Correct,
            default => UserQuestionStatus::Incorrect,
        };

        $status->forceFill(['status' => $restored])->save();
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
