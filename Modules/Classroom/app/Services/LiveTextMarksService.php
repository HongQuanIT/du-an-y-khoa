<?php

declare(strict_types=1);

namespace Modules\Classroom\Services;

use Illuminate\Support\Str;
use Modules\Classroom\Models\LiveSession;

final class LiveTextMarksService
{
    public const COLORS = ['yellow', 'green', 'pink', 'blue'];

    public const MAX_MARKS = 80;

    /**
     * @return list<array{
     *   id: string,
     *   question_id: string,
     *   target: string,
     *   option_id: int|null,
     *   start: int,
     *   end: int,
     *   color: string
     * }>
     */
    public function marks(LiveSession $session): array
    {
        $raw = $session->text_marks ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $normalized = $this->normalizeMark($row);
            if ($normalized !== null) {
                $out[] = $normalized;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array<string, mixed>>
     */
    public function add(LiveSession $session, array $input): array
    {
        $mark = $this->normalizeMark([
            'id' => (string) Str::ulid(),
            'question_id' => $input['question_id'] ?? null,
            'target' => $input['target'] ?? null,
            'option_id' => $input['option_id'] ?? null,
            'start' => $input['start'] ?? null,
            'end' => $input['end'] ?? null,
            'color' => $input['color'] ?? null,
        ]);
        abort_if($mark === null, 422, 'Mark không hợp lệ.');

        $questionIds = $session->questionIds();
        abort_unless(in_array($mark['question_id'], $questionIds, true), 422, 'Câu hỏi không thuộc buổi live.');

        $marks = $this->marks($session);
        abort_if(count($marks) >= self::MAX_MARKS, 422, 'Đã đạt giới hạn tô màu.');

        $marks[] = $mark;
        $session->update(['text_marks' => $marks]);

        return $marks;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function remove(LiveSession $session, string $markId): array
    {
        $marks = array_values(array_filter(
            $this->marks($session),
            static fn (array $m): bool => $m['id'] !== $markId,
        ));
        $session->update(['text_marks' => $marks]);

        return $marks;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function clearForQuestion(LiveSession $session, string $questionId): array
    {
        $marks = array_values(array_filter(
            $this->marks($session),
            static fn (array $m): bool => $m['question_id'] !== $questionId,
        ));
        $session->update(['text_marks' => $marks]);

        return $marks;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{
     *   id: string,
     *   question_id: string,
     *   target: string,
     *   option_id: int|null,
     *   start: int,
     *   end: int,
     *   color: string
     * }|null
     */
    private function normalizeMark(array $row): ?array
    {
        $id = isset($row['id']) ? trim((string) $row['id']) : '';
        $questionId = isset($row['question_id']) ? trim((string) $row['question_id']) : '';
        $target = isset($row['target']) ? (string) $row['target'] : '';
        $color = isset($row['color']) ? (string) $row['color'] : '';
        $start = isset($row['start']) ? (int) $row['start'] : -1;
        $end = isset($row['end']) ? (int) $row['end'] : -1;
        $optionId = array_key_exists('option_id', $row) && $row['option_id'] !== null
            ? (int) $row['option_id']
            : null;

        if ($id === '' || $questionId === '') {
            return null;
        }
        if (! in_array($target, ['stem', 'option'], true)) {
            return null;
        }
        if (! in_array($color, self::COLORS, true)) {
            return null;
        }
        if ($start < 0 || $end <= $start || ($end - $start) > 2000) {
            return null;
        }
        if ($target === 'option' && ($optionId === null || $optionId <= 0)) {
            return null;
        }
        if ($target === 'stem') {
            $optionId = null;
        }

        return [
            'id' => $id,
            'question_id' => $questionId,
            'target' => $target,
            'option_id' => $optionId,
            'start' => $start,
            'end' => $end,
            'color' => $color,
        ];
    }
}
