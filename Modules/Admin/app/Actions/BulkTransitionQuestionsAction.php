<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Permission;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Support\QuestionAccess;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\ReviewerFlag;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Support\QuestionReviewerFlagCycle;
use Throwable;

/**
 * Bulk publish only: pending_publish + 1 pipeline cycle + 2 green flags.
 * ≥2 cycles / red flags / other states → skip (manual on form).
 *
 * @phpstan-type BulkSkip array{id: string, code: string, reason: string}
 * @phpstan-type BulkResult array{
 *   published: int,
 *   skipped: list<BulkSkip>,
 *   failed: list<BulkSkip>,
 *   message: string
 * }
 */
final class BulkTransitionQuestionsAction
{
    use AsAction;

    public const MAX_IDS = 20;

    public function __construct(
        private readonly TransitionQuestionStatusAction $transition,
        private readonly QuestionReviewerFlagCycle $flagCycle,
    ) {}

    /**
     * @param  list<string>  $ids
     * @return BulkResult
     */
    public function handle(User $actor, array $ids): array
    {
        if (! $actor->can(Permission::QuestionPublish->value)) {
            abort(403, 'Cần quyền question.publish.');
        }

        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($id): string => trim((string) $id),
            $ids,
        ), static fn (string $id): bool => $id !== '')));

        if ($ids === []) {
            throw ValidationException::withMessages([
                'ids' => 'Chưa chọn câu hỏi nào.',
            ]);
        }

        if (count($ids) > self::MAX_IDS) {
            throw ValidationException::withMessages([
                'ids' => 'Tối đa '.self::MAX_IDS.' câu mỗi lần xuất bản hàng loạt.',
            ]);
        }

        $questions = Question::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy(fn (Question $q): string => (string) $q->getKey());

        $published = 0;
        /** @var list<BulkSkip> $skipped */
        $skipped = [];
        /** @var list<BulkSkip> $failed */
        $failed = [];

        foreach ($ids as $id) {
            $question = $questions->get($id);
            if (! $question instanceof Question) {
                $skipped[] = [
                    'id' => $id,
                    'code' => '—',
                    'reason' => 'Không tìm thấy câu hỏi',
                ];

                continue;
            }

            if (! QuestionAccess::canView($actor, $question)) {
                $skipped[] = $this->skip($question, 'Không có quyền xem câu này');

                continue;
            }

            $eligibility = $this->bulkPublishSkipReason($question);
            if ($eligibility !== null) {
                $skipped[] = $this->skip($question, $eligibility);

                continue;
            }

            try {
                $this->transition->handle($actor, $question, QuestionStatus::Published);
                $published++;
            } catch (ValidationException $e) {
                $skipped[] = $this->skip($question, $this->firstValidationMessage($e));
            } catch (Throwable $e) {
                $failed[] = $this->skip($question, 'Lỗi hệ thống khi xuất bản');
                report($e);
            }
        }

        $parts = [];
        if ($published > 0) {
            $parts[] = 'Đã XB '.$published;
        }
        if ($skipped !== []) {
            $parts[] = 'Bỏ qua '.count($skipped);
        }
        if ($failed !== []) {
            $parts[] = 'Lỗi '.count($failed);
        }

        return [
            'published' => $published,
            'skipped' => $skipped,
            'failed' => $failed,
            'message' => $parts === [] ? 'Không có câu nào được xử lý.' : implode(' · ', $parts).'.',
        ];
    }

    /**
     * Bulk XB chỉ khi: chờ XB + đúng 1 vòng pipeline + đủ 2 cờ xanh (không đỏ).
     */
    public function isEligibleForBulkPublish(Question $question): bool
    {
        return $this->bulkPublishSkipReason($question) === null;
    }

    public function bulkPublishSkipReason(Question $question): ?string
    {
        if ($question->status !== QuestionStatus::PendingPublish) {
            return 'Không ở trạng thái chờ xuất bản';
        }

        if ((int) $question->currentPipelineReviewCycle() !== 1) {
            return 'Chỉ XB hàng loạt khi đúng 1 vòng — cần duyệt thủ công';
        }

        if ($this->flagCycle->hasRedFlag($question)) {
            return 'Có cờ đỏ — duyệt thủ công trên form';
        }

        if (! $this->hasTwoGreenFlags($question)) {
            return 'Cần đủ 2 cờ xanh';
        }

        return null;
    }

    private function hasTwoGreenFlags(Question $question): bool
    {
        if (! $this->flagCycle->hasRequiredFlags($question)) {
            return false;
        }

        $slot1 = $question->reviewer_1_flag instanceof ReviewerFlag
            ? $question->reviewer_1_flag
            : ReviewerFlag::tryFrom((string) $question->reviewer_1_flag);
        $slot2 = $question->reviewer_2_flag instanceof ReviewerFlag
            ? $question->reviewer_2_flag
            : ReviewerFlag::tryFrom((string) $question->reviewer_2_flag);

        return $slot1 === ReviewerFlag::Green && $slot2 === ReviewerFlag::Green;
    }

    /**
     * @return BulkSkip
     */
    private function skip(Question $question, string $reason): array
    {
        return [
            'id' => (string) $question->getKey(),
            'code' => (string) ($question->code ?: '—'),
            'reason' => $reason,
        ];
    }

    private function firstValidationMessage(ValidationException $e): string
    {
        $messages = $e->errors();
        foreach ($messages as $fieldMessages) {
            if (is_array($fieldMessages) && isset($fieldMessages[0]) && is_string($fieldMessages[0])) {
                return $fieldMessages[0];
            }
        }

        return 'Không đủ điều kiện xuất bản';
    }
}
