<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;

/**
 * Hủy bản nháp đang mở trên editor.
 *
 * Câu chưa từng xuất bản: xóa bản nháp.
 * Câu đã có bản đang phát hành: bỏ working copy và trả nội dung về snapshot đó.
 */
final class DiscardQuestionDraftAction
{
    public function __construct(
        private readonly RestoreQuestionVersionAction $restore,
        private readonly RequestQuestionDeletionAction $delete,
    ) {}

    public function handle(User $actor, Question $question): string
    {
        $status = $question->status instanceof QuestionStatus
            ? $question->status
            : QuestionStatus::tryFrom((string) $question->status);

        if ($status !== QuestionStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ hủy được khi câu đang ở bản nháp.',
            ]);
        }

        if ((int) $question->published_version >= 1) {
            $this->restore->discardWorkingCopy($actor, $question);

            return 'restored';
        }

        if ((int) $question->version === 0) {
            $this->delete->handle($actor, $question);

            return 'deleted';
        }

        throw ValidationException::withMessages([
            'status' => 'Bản nháp này từng được xuất bản. Không thể xóa bằng Hủy bỏ.',
        ]);
    }
}
