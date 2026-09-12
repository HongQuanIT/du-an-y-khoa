<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\QuestionBank\Enums\QuestionImportBatchStatus;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionImportBatch;
use Modules\QuestionBank\Models\Tag;
use Modules\QuestionBank\Support\QuestionImportSchema;
use Modules\QuestionBank\Support\QuestionSpreadsheet;

/**
 * Re-read the uploaded file, apply column map, and classify each row.
 */
final class PrepareQuestionImportPreviewAction
{
    use AsAction;

    public function __construct(
        private readonly QuestionSpreadsheet $spreadsheet,
    ) {}

    /**
     * @param  array<string, int|string|null>|null  $columnMap
     * @return array{
     *     total: int,
     *     valid: int,
     *     invalid: int,
     *     invalid_codes: list<string>,
     *     rows: list<array{line: int, ok: bool, action: ?string, errors: list<string>, values: array<string, string>, payload?: array<string, mixed>}>
     * }
     */
    public function handle(QuestionImportBatch $batch, ?array $columnMap = null): array
    {
        $path = Storage::disk('local')->path($batch->disk_path);
        if (! is_file($path)) {
            throw ValidationException::withMessages([
                'file' => 'Tệp import không còn trên máy chủ. Tải lại tệp.',
            ]);
        }

        $parsed = $this->spreadsheet->read($path);
        $map = $columnMap ?? $batch->column_map ?? QuestionImportSchema::autoMap($parsed['headers']);
        $this->assertRequiredMapped($map);

        $extracted = [];
        foreach ($parsed['rows'] as $offset => $raw) {
            $extracted[] = [
                'line' => $offset + 2,
                'values' => QuestionImportSchema::extractRow($raw, $map),
            ];
        }

        $codes = collect($extracted)
            ->pluck('values.code')
            ->filter(fn (mixed $code): bool => is_string($code) && $code !== '')
            ->unique()
            ->values()
            ->all();

        /** @var Collection<string, Question> $existingByCode */
        $existingByCode = $codes === []
            ? collect()
            : Question::query()->whereIn('code', $codes)->get()->keyBy('code');

        $seenCodes = [];
        $rows = [];
        foreach ($extracted as $item) {
            $rows[] = $this->validateValues($item['values'], $item['line'], $existingByCode, $seenCodes);
        }

        $valid = collect($rows)->where('ok', true)->count();
        $invalid = count($rows) - $valid;
        $invalidCodes = collect($rows)
            ->filter(fn (array $row): bool => ! $row['ok'] && filled($row['values']['code'] ?? null))
            ->filter(fn (array $row): bool => collect($row['errors'])->contains(
                fn (string $error): bool => str_contains($error, 'Không tìm thấy mã câu hỏi'),
            ))
            ->pluck('values.code')
            ->unique()
            ->values()
            ->all();

        $preview = [
            'total' => count($rows),
            'valid' => $valid,
            'invalid' => $invalid,
            'invalid_codes' => $invalidCodes,
            'rows' => $rows,
        ];

        if ($batch->status !== QuestionImportBatchStatus::Done) {
            $batch->forceFill([
                'column_map' => $map,
                'source_headers' => $parsed['headers'],
                'status' => QuestionImportBatchStatus::Validated,
                'stats' => [
                    'total' => $preview['total'],
                    'valid' => $valid,
                    'invalid' => $invalid,
                    'invalid_codes' => $invalidCodes,
                    'created' => (int) ($batch->stats['created'] ?? 0),
                    'updated' => (int) ($batch->stats['updated'] ?? 0),
                ],
            ])->save();
        }

        $this->writeErrorReport($batch, $parsed['headers'], $parsed['rows'], $rows);

        return $preview;
    }

    /**
     * @param  array<string, int|string|null>  $map
     */
    private function assertRequiredMapped(array $map): void
    {
        $missing = [];
        foreach (QuestionImportSchema::fields() as $field => $meta) {
            if (! $meta['required']) {
                continue;
            }
            if (! isset($map[$field]) || $map[$field] === '' || $map[$field] === null) {
                $missing[] = $meta['label'];
            }
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'column_map' => 'Chưa ánh xạ đủ cột bắt buộc: '.implode(', ', $missing).'.',
            ]);
        }
    }

    /**
     * @param  array<string, string>  $values
     * @param  Collection<string, Question>  $existingByCode
     * @param  array<string, int>  $seenCodes
     * @return array{line: int, ok: bool, action: ?string, errors: list<string>, values: array<string, string>, payload?: array<string, mixed>}
     */
    private function validateValues(array $values, int $line, Collection $existingByCode, array &$seenCodes): array
    {
        $errors = [];
        $existing = null;
        $action = 'create';

        $code = $values['code'];
        if ($code !== '') {
            if (isset($seenCodes[$code])) {
                $errors[] = 'Mã câu hỏi trùng trong tệp: '.$code.'.';
            } else {
                $seenCodes[$code] = $line;
            }

            $existing = $existingByCode->get($code);
            if ($existing === null) {
                $errors[] = 'Không tìm thấy mã câu hỏi: '.$code.'. Chỉ điền mã đã có trên hệ thống (để cập nhật) hoặc để trống (để tạo mới).';
            } else {
                $action = 'update';
                $blocked = $this->blockedUpdateReason($existing);
                if ($blocked !== null) {
                    $errors[] = $blocked;
                }
            }
        }

        if ($values['stem'] === '') {
            $errors[] = 'Thiếu đề bài.';
        }

        $options = [];
        $labels = [];
        foreach (range(0, QuestionImportSchema::MAX_OPTIONS - 1) as $index) {
            $letter = chr(65 + $index);
            $key = 'option_'.strtolower($letter);
            $content = $values[$key];
            if ($content === '') {
                continue;
            }
            $labels[] = $letter;
            $options[] = [
                'content' => $content,
                'is_correct' => false,
                'explanation' => $values[$key.'_explanation'] !== ''
                    ? $values[$key.'_explanation']
                    : null,
            ];
        }

        if (count($options) < 2) {
            $errors[] = 'Cần ít nhất 2 đáp án.';
        }

        $correct = strtoupper(trim($values['correct']));
        $correct = preg_replace('/[^A-E]/', '', $correct) ?? '';
        if (strlen($correct) !== 1) {
            $errors[] = 'Đáp án đúng phải là một chữ A–E.';
        } elseif (! in_array($correct, $labels, true)) {
            $errors[] = 'Đáp án đúng không khớp đáp án đã nhập.';
        } else {
            foreach ($options as $index => $option) {
                $options[$index]['is_correct'] = $labels[$index] === $correct;
            }
            if ($values['explanation'] !== '') {
                foreach ($options as $index => $option) {
                    if ($option['is_correct'] && blank($option['explanation'])) {
                        $options[$index]['explanation'] = $values['explanation'];
                    }
                }
            }
        }

        $difficulty = QuestionImportSchema::parseDifficulty($values['difficulty']);
        if ($difficulty === null) {
            $errors[] = 'Độ khó không hợp lệ.';
        }

        $lessonTokens = QuestionImportSchema::splitList($values['lesson_slugs']);
        $lessonIds = $this->resolveLessons($lessonTokens);
        if ($lessonTokens === []) {
            $errors[] = 'Cần ít nhất một bài học (slug hoặc mã).';
        } elseif (count($lessonIds) !== count($lessonTokens)) {
            $errors[] = 'Không tìm thấy bài học: '.$this->unresolved($lessonTokens, $lessonIds).'.';
        }

        $tagTokens = QuestionImportSchema::splitList($values['tag_slugs']);
        $tagIds = $this->resolveTags($tagTokens);
        if ($tagTokens !== [] && count($tagIds) !== count($tagTokens)) {
            $errors[] = 'Không tìm thấy thẻ: '.$this->unresolved($tagTokens, $tagIds, 'tag').'.';
        }

        if ($errors !== []) {
            return [
                'line' => $line,
                'ok' => false,
                'action' => $code !== '' && $existing === null ? null : $action,
                'errors' => $errors,
                'values' => $values,
            ];
        }

        return [
            'line' => $line,
            'ok' => true,
            'action' => $action,
            'errors' => [],
            'values' => $values,
            'payload' => [
                'existing_id' => $existing?->getKey(),
                'code' => $existing === null ? ($code !== '' ? $code : null) : null,
                'stem' => $values['stem'],
                'stem_image_path' => null,
                'key_info' => QuestionImportSchema::splitList(str_replace(["\r\n", "\n"], '|', $values['hints'])),
                'attending_tip' => $values['attending_tip'] !== '' ? $values['attending_tip'] : null,
                'difficulty' => $difficulty->value,
                'lesson_ids' => $lessonIds,
                'tag_ids' => $tagIds,
                'is_free' => QuestionImportSchema::parseBoolean($values['is_free']),
                'exam_flag' => QuestionImportSchema::parseBoolean($values['exam_flag']),
                'options' => $options,
            ],
        ];
    }

    private function blockedUpdateReason(Question $question): ?string
    {
        $code = (string) $question->code;

        return match ($question->status) {
            QuestionStatus::PendingPublish => 'Câu '.$code.' đang chờ xuất bản, không được import đè.',
            QuestionStatus::Retired => 'Câu '.$code.' đã ngừng dùng, không được import đè.',
            QuestionStatus::Rejected => 'Câu '.$code.' đã bị từ chối, không được import đè.',
            default => null,
        };
    }

    /**
     * @param  list<string>  $tokens
     * @return list<int>
     */
    private function resolveLessons(array $tokens): array
    {
        if ($tokens === []) {
            return [];
        }

        return Lesson::query()
            ->where(function ($query) use ($tokens): void {
                $query->whereIn('slug', $tokens)->orWhereIn('code', $tokens);
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $tokens
     * @return list<int>
     */
    private function resolveTags(array $tokens): array
    {
        if ($tokens === []) {
            return [];
        }

        return Tag::query()
            ->whereIn('slug', $tokens)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $tokens
     * @param  list<int>  $ids
     */
    private function unresolved(array $tokens, array $ids, string $type = 'lesson'): string
    {
        unset($ids, $type);

        return implode(', ', $tokens);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rawRows
     * @param  list<array{line: int, ok: bool, errors: list<string>, values: array<string, string>}>  $rows
     */
    private function writeErrorReport(
        QuestionImportBatch $batch,
        array $headers,
        array $rawRows,
        array $rows,
    ): void {
        $invalid = array_values(array_filter($rows, fn (array $row): bool => ! $row['ok']));
        if ($invalid === []) {
            if ($batch->error_report_path) {
                Storage::disk('local')->delete($batch->error_report_path);
            }
            $batch->forceFill(['error_report_path' => null])->save();

            return;
        }

        $exportHeaders = array_merge($headers, ['errors']);
        $exportRows = [];
        foreach ($invalid as $row) {
            $source = $rawRows[$row['line'] - 2] ?? [];
            $exportRows[] = array_merge(
                array_pad($source, count($headers), ''),
                [implode(' | ', $row['errors'])],
            );
        }

        $relative = 'question-imports/'.$batch->getKey().'-errors.csv';
        $absolute = Storage::disk('local')->path($relative);
        @mkdir(dirname($absolute), 0755, true);
        $this->spreadsheet->writeCsv($absolute, $exportHeaders, $exportRows);
        $batch->forceFill(['error_report_path' => $relative])->save();
    }
}
