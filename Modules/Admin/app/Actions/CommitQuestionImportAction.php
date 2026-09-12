<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Enums\AuditAction;
use Modules\Admin\Support\Auditor;
use Modules\QuestionBank\Enums\QuestionImportBatchStatus;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\QuestionImportBatch;

/**
 * Create draft questions from a validated import batch. Never publishes.
 */
final class CommitQuestionImportAction
{
    use AsAction;

    public function __construct(
        private readonly PrepareQuestionImportPreviewAction $preview,
        private readonly SaveAdminQuestionAction $save,
    ) {}

    /**
     * @return array{created: int, skipped: int}
     */
    public function handle(User $actor, QuestionImportBatch $batch): array
    {
        if ($batch->status === QuestionImportBatchStatus::Done) {
            throw ValidationException::withMessages([
                'batch' => 'Lô import này đã được ghi. Tải tệp mới nếu cần nhập thêm.',
            ]);
        }

        $preview = $this->preview->handle($batch, $batch->column_map);
        $valid = array_values(array_filter($preview['rows'], fn (array $row): bool => $row['ok']));

        if ($valid === []) {
            throw ValidationException::withMessages([
                'batch' => 'Không có dòng hợp lệ để import.',
            ]);
        }

        $created = 0;

        DB::transaction(function () use ($actor, $batch, $valid, $preview, &$created): void {
            foreach ($valid as $row) {
                $payload = $row['payload'] ?? null;
                if (! is_array($payload)) {
                    continue;
                }

                $question = $this->save->handle($actor, null, $payload);
                $question->forceFill([
                    'import_batch_id' => $batch->getKey(),
                    'status' => QuestionStatus::Draft,
                    'version' => 0,
                    'published_version' => null,
                    'publisher_id' => null,
                    'instructor_id' => null,
                ])->save();

                if ($question->status !== QuestionStatus::Draft) {
                    $question->forceFill(['status' => QuestionStatus::Draft])->save();
                }

                $created++;
            }

            $batch->forceFill([
                'status' => QuestionImportBatchStatus::Done,
                'committed_at' => now(),
                'stats' => array_merge($batch->stats ?? [], [
                    'created' => $created,
                    'skipped' => (int) ($preview['invalid'] ?? 0),
                ]),
            ])->save();
        });

        Auditor::record(
            AuditAction::QuestionImported,
            $actor,
            $batch,
            metadata: [
                'created' => $created,
                'skipped' => $preview['invalid'],
                'filename' => $batch->original_filename,
            ],
        );

        return [
            'created' => $created,
            'skipped' => $preview['invalid'],
        ];
    }
}
