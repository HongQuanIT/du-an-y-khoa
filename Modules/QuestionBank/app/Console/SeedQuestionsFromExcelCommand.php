<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Admin\Actions\CaptureQuestionVersionAction;
use Modules\Admin\Actions\CommitQuestionImportAction;
use Modules\Admin\Actions\PrepareQuestionImportPreviewAction;
use Modules\QuestionBank\Enums\QuestionImportBatchStatus;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionImportBatch;
use Modules\QuestionBank\Models\Subject;
use Modules\QuestionBank\Support\QuestionImportSchema;
use Modules\QuestionBank\Support\QuestionSpreadsheet;

/**
 * Dev/QA helper: import Excel/CSV via the real import stack, then force-publish for QBank testing.
 *
 * Does not run the full instructor + 2-reviewer pipeline (too slow for bulk seed).
 * Ownership stays on the editor; format/lesson validation matches /admin/questions/import.
 */
final class SeedQuestionsFromExcelCommand extends Command
{
    protected $signature = 'question-bank:seed-from-excel
        {path? : Path to xlsx/csv (default: seeder data/qbank-demo.xlsx)}
        {--editor=editor@medlearn.local : Content editor email (created_by)}
        {--publisher=admin@medlearn.local : Publisher email (publisher_id)}
        {--no-ensure-lessons : Do not auto-create missing lesson slugs}
        {--dry-run : Validate only, no DB writes}
        {--skip-publish : Import as draft only}';

    protected $description = 'Seed questions from Excel sample to published for QBank testing';

    public function handle(
        QuestionSpreadsheet $spreadsheet,
        PrepareQuestionImportPreviewAction $preview,
        CommitQuestionImportAction $commit,
        CaptureQuestionVersionAction $captureVersion,
    ): int {
        $path = $this->resolvePath((string) ($this->argument('path') ?? ''));
        if ($path === null) {
            return self::FAILURE;
        }

        $editor = User::query()->where('email', (string) $this->option('editor'))->first();
        $publisher = User::query()->where('email', (string) $this->option('publisher'))->first();
        if ($editor === null || $publisher === null) {
            $this->error('Thiếu user editor/publisher. Chạy UserSeeder trước.');

            return self::FAILURE;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'xlsx');
        $format = $extension === 'csv' ? 'csv' : 'xlsx';

        try {
            $parsed = $spreadsheet->read($path);
        } catch (\Throwable $e) {
            $this->error($e->getMessage() ?: 'Không đọc được tệp.');

            return self::FAILURE;
        }

        if ($parsed['headers'] === [] || $parsed['rows'] === []) {
            $this->error('Tệp không có dòng dữ liệu.');

            return self::FAILURE;
        }

        $map = QuestionImportSchema::autoMap($parsed['headers']);
        if (! $this->option('no-ensure-lessons')) {
            $createdLessons = $this->ensureMissingLessons($parsed['rows'], $map);
            if ($createdLessons > 0) {
                $this->info("Đã tạo {$createdLessons} bài học thiếu (slug từ Excel).");
            }
        }

        if ($this->option('dry-run')) {
            return $this->dryRunPreview($path, $parsed, $map, $preview, $editor);
        }

        $relative = 'question-imports/seed-'.now()->format('YmdHis').'-'.Str::random(6).'.'.$format;
        Storage::disk('local')->put($relative, file_get_contents($path) ?: '');

        $batch = QuestionImportBatch::query()->create([
            'uploaded_by' => $editor->getKey(),
            'original_filename' => basename($path),
            'disk_path' => $relative,
            'format' => $format,
            'status' => QuestionImportBatchStatus::Mapped,
            'source_headers' => $parsed['headers'],
            'column_map' => $map,
        ]);

        try {
            $stats = $preview->handle($batch, $map);
        } catch (\Throwable $e) {
            $batch->forceFill(['status' => QuestionImportBatchStatus::Failed])->save();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line(sprintf(
            'Preview: %d dòng · hợp lệ %d · lỗi %d',
            $stats['total'],
            $stats['valid'],
            $stats['invalid'],
        ));

        if ($stats['valid'] === 0) {
            $this->printInvalidSample($stats['rows']);
            $this->error('Không có dòng hợp lệ để import.');

            return self::FAILURE;
        }

        if ($stats['invalid'] > 0) {
            $this->warn('Một số dòng sẽ bị bỏ qua:');
            $this->printInvalidSample($stats['rows']);
        }

        try {
            $result = $commit->handle($editor, $batch->fresh());
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Import xong: tạo %d · cập nhật %d · bỏ qua %d (batch %s)',
            $result['created'],
            $result['updated'],
            $result['skipped'],
            $batch->getKey(),
        ));

        if ($this->option('skip-publish')) {
            $this->comment('Đã dừng ở draft (--skip-publish).');

            return self::SUCCESS;
        }

        $published = $this->publishBatch(
            $batch->fresh(),
            $publisher,
            $captureVersion,
        );

        $this->info("Đã xuất bản {$published} câu → QBank (status=published, version=1).");

        return self::SUCCESS;
    }

    private function resolvePath(string $argument): ?string
    {
        $candidates = array_values(array_filter([
            $argument !== '' ? $argument : null,
            $argument !== '' && ! str_starts_with($argument, '/')
                ? base_path($argument)
                : null,
            module_path('QuestionBank', 'database/seeders/data/qbank-demo.xlsx'),
            base_path('Modules/QuestionBank/database/seeders/data/qbank-demo.xlsx'),
        ]));

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && is_file($candidate)) {
                $this->line('File: '.$candidate);

                return $candidate;
            }
        }

        $this->error('Không tìm thấy file Excel. Truyền path hoặc đặt qbank-demo.xlsx trong seeder data.');

        return null;
    }

    /**
     * @param  list<list<string>>  $rows
     * @param  array<string, int|string|null>  $map
     */
    private function ensureMissingLessons(array $rows, array $map): int
    {
        $lessonIndex = $map['lesson_slugs'] ?? null;
        if ($lessonIndex === null || $lessonIndex === '') {
            return 0;
        }

        $slugs = [];
        foreach ($rows as $row) {
            $raw = trim((string) ($row[(int) $lessonIndex] ?? ''));
            foreach (QuestionImportSchema::splitList($raw) as $token) {
                $slugs[$token] = true;
            }
        }

        if ($slugs === []) {
            return 0;
        }

        $existing = Lesson::query()
            ->whereIn('slug', array_keys($slugs))
            ->pluck('slug')
            ->all();
        $missing = array_values(array_diff(array_keys($slugs), $existing));
        if ($missing === []) {
            return 0;
        }

        $subject = Subject::query()->firstOrCreate(
            ['slug' => 'seed-import'],
            [
                'name' => 'Seed import',
                'status' => TaxonomyStatus::Active,
                'sort_order' => 9999,
            ],
        );

        $created = 0;
        $sort = (int) Lesson::query()->max('sort_order') + 1;
        foreach ($missing as $slug) {
            $lesson = Lesson::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $this->titleFromSlug($slug),
                    'status' => TaxonomyStatus::Active,
                    'sort_order' => $sort++,
                    'description' => 'Tạo tự động khi seed Excel (slug thiếu trong taxonomy).',
                ],
            );
            $subject->lessons()->syncWithoutDetaching([
                $lesson->id => ['sort_order' => $lesson->sort_order],
            ]);
            $created++;
            $this->line('  + lesson: '.$slug);
        }

        return $created;
    }

    private function titleFromSlug(string $slug): string
    {
        $name = str_replace(['-', '_'], ' ', $slug);

        return Str::ucfirst($name);
    }

    /**
     * @param  array{headers: list<string>, rows: list<list<string>>}  $parsed
     * @param  array<string, int|string|null>  $map
     */
    private function dryRunPreview(
        string $path,
        array $parsed,
        array $map,
        PrepareQuestionImportPreviewAction $preview,
        User $editor,
    ): int {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'xlsx');
        $format = $extension === 'csv' ? 'csv' : 'xlsx';
        $relative = 'question-imports/dry-run-'.Str::random(8).'.'.$format;
        Storage::disk('local')->put($relative, file_get_contents($path) ?: '');

        $batch = QuestionImportBatch::query()->create([
            'uploaded_by' => $editor->getKey(),
            'original_filename' => basename($path),
            'disk_path' => $relative,
            'format' => $format,
            'status' => QuestionImportBatchStatus::Mapped,
            'source_headers' => $parsed['headers'],
            'column_map' => $map,
        ]);

        try {
            $stats = $preview->handle($batch, $map);
        } finally {
            Storage::disk('local')->delete($relative);
            $batch->delete();
        }

        $this->info(sprintf(
            'Dry-run: %d dòng · hợp lệ %d · lỗi %d',
            $stats['total'],
            $stats['valid'],
            $stats['invalid'],
        ));
        $this->printInvalidSample($stats['rows'], 30);

        return $stats['invalid'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<array{line: int, ok: bool, errors: list<string>}>  $rows
     */
    private function printInvalidSample(array $rows, int $limit = 15): void
    {
        $invalid = array_values(array_filter($rows, fn (array $row): bool => ! $row['ok']));
        foreach (array_slice($invalid, 0, $limit) as $row) {
            $this->line(sprintf(
                '  L%d: %s',
                $row['line'],
                implode(' | ', $row['errors']),
            ));
        }
        if (count($invalid) > $limit) {
            $this->line('  … và '.(count($invalid) - $limit).' dòng lỗi khác');
        }
    }

    private function publishBatch(
        QuestionImportBatch $batch,
        User $publisher,
        CaptureQuestionVersionAction $captureVersion,
    ): int {
        $count = 0;

        Question::withoutSyncingToSearch(function () use ($batch, $publisher, $captureVersion, &$count): void {
            Question::query()
                ->where('import_batch_id', $batch->getKey())
                ->orderBy('created_at')
                ->chunkById(50, function ($questions) use ($publisher, $captureVersion, &$count): void {
                    foreach ($questions as $question) {
                        /** @var Question $question */
                        if ($question->status === QuestionStatus::Published
                            && (int) $question->published_version >= 1) {
                            continue;
                        }

                        $version = max(1, (int) $question->version);

                        $question->forceFill([
                            'status' => QuestionStatus::Published,
                            'version' => $version,
                            'published_version' => $version,
                            'publisher_id' => $publisher->getKey(),
                            'updated_by' => $publisher->getKey(),
                            'rejection_reason' => null,
                            'rejected_by_role' => null,
                        ])->save();

                        $captureVersion->handle(
                            $question->fresh(['options', 'lessons', 'tags']),
                            $publisher,
                            'publish',
                            reviewPipeline: [
                                'seed' => true,
                                'note' => 'Force-publish từ question-bank:seed-from-excel (bỏ qua pipeline duyệt).',
                            ],
                        );

                        $count++;
                    }
                });
        });

        return $count;
    }
}
