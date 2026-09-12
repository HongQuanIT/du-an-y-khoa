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
use Modules\QuestionBank\Enums\QuestionReviewAction;
use Modules\QuestionBank\Enums\QuestionReviewStatus;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionImportBatch;
use Modules\QuestionBank\Support\QuestionInstructorReviewCycle;

/**
 * Create or update questions from a validated import batch. Never publishes.
 */
final class CommitQuestionImportAction
{
    use AsAction;

    public function __construct(
        private readonly PrepareQuestionImportPreviewAction $preview,
        private readonly SaveAdminQuestionAction $save,
        private readonly QuestionInstructorReviewCycle $reviewCycle,
    ) {}

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function handle(User $actor, QuestionImportBatch $batch): array
    {
        if ($batch->status === QuestionImportBatchStatus::Done) {
            throw ValidationException::withMessages([
                'batch' => 'Lô import này đã được ghi. Tải tệp mới nếu cần nhập thêm.',
            ]);
        }

        $preview = $this->preview->handle($batch, $batch->column_map);
        $invalidCodes = $preview['invalid_codes'] ?? [];
        if ($invalidCodes !== []) {
            throw ValidationException::withMessages([
                'batch' => 'Không import được vì mã câu hỏi không tồn tại: '.implode(', ', $invalidCodes).'. Sửa mã hoặc để trống cột code rồi tải lại.',
            ]);
        }

        $valid = array_values(array_filter($preview['rows'], fn (array $row): bool => $row['ok']));

        if ($valid === []) {
            throw ValidationException::withMessages([
                'batch' => 'Không có dòng hợp lệ để import.',
            ]);
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($actor, $batch, $valid, $preview, &$created, &$updated): void {
            foreach ($valid as $row) {
                $payload = $row['payload'] ?? null;
                if (! is_array($payload)) {
                    continue;
                }

                $existingId = $payload['existing_id'] ?? null;
                unset($payload['existing_id']);

                $existing = is_string($existingId) && $existingId !== ''
                    ? Question::query()->whereKey($existingId)->first()
                    : null;

                if (filled($existingId) && $existing === null) {
                    continue;
                }

                $question = $this->save->handle($actor, $existing, $payload);
                $question->forceFill([
                    'import_batch_id' => $batch->getKey(),
                ]);

                if ($existing === null) {
                    $question->forceFill([
                        'status' => QuestionStatus::Draft,
                        'version' => 0,
                        'published_version' => null,
                        'publisher_id' => null,
                        'instructor_id' => null,
                    ]);
                    $created++;
                } else {
                    $this->returnImportedUpdateToDraft($question);
                    $updated++;
                }

                $question->save();
            }

            $batch->forceFill([
                'status' => QuestionImportBatchStatus::Done,
                'committed_at' => now(),
                'stats' => array_merge($batch->stats ?? [], [
                    'created' => $created,
                    'updated' => $updated,
                    'skipped' => (int) ($preview['invalid'] ?? 0),
                    'invalid_codes' => $preview['invalid_codes'] ?? [],
                ]),
            ])->save();
        });

        Auditor::record(
            AuditAction::QuestionImported,
            $actor,
            $batch,
            metadata: [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $preview['invalid'],
                'invalid_codes' => $preview['invalid_codes'] ?? [],
                'filename' => $batch->original_filename,
            ],
        );

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $preview['invalid'],
        ];
    }

    private function returnImportedUpdateToDraft(Question $question): void
    {
        if ($question->status !== QuestionStatus::InReview) {
            return;
        }

        $question->reviewRequests()
            ->where('status', QuestionReviewStatus::Pending->value)
            ->where('action', QuestionReviewAction::Create->value)
            ->delete();
        $this->reviewCycle->clearSlots($question);
        $question->forceFill(['status' => QuestionStatus::Draft]);
    }
}
