<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Admin\Actions\ReviewQuestionChangeAction;
use Modules\Admin\Actions\SummarizeQuestionDeletionImpactAction;
use Modules\QuestionBank\Enums\QuestionReviewAction;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\QuestionReviewRequest;

final class QuestionReviewController extends Controller
{
    public function show(QuestionReviewRequest $reviewRequest): View
    {
        abort_unless($this->actor()->canAny(['question.reject', 'question.publish']), 403);

        $reviewRequest->load([
            'question.options',
            'question.lessons:id,name',
            'requester:id,name,email',
        ]);
        $lessonNames = Lesson::query()
            // Accept both the new key and the legacy key for old payloads.
            ->whereIn('id', array_map('intval', (array) ($reviewRequest->payload['lesson_ids']
                ?? $reviewRequest->payload['medical_taxonomy_node_ids']
                ?? [])))
            ->pluck('name', 'id');
        $deletionImpact = in_array($reviewRequest->action, [QuestionReviewAction::Delete, QuestionReviewAction::Retire], true)
            && $reviewRequest->question !== null
            ? app(SummarizeQuestionDeletionImpactAction::class)->handle($reviewRequest->question)
            : null;

        return view('admin::questions.review', compact('reviewRequest', 'lessonNames', 'deletionImpact'));
    }

    public function approve(
        Request $request,
        QuestionReviewRequest $reviewRequest,
        ReviewQuestionChangeAction $action,
    ): RedirectResponse {
        $rules = ['review_note' => ['nullable', 'string', 'max:2000']];
        if ($reviewRequest->action === QuestionReviewAction::Delete) {
            $rules['confirm_deletion'] = ['accepted'];
        }
        $data = $request->validate($rules, [
            'confirm_deletion.accepted' => 'Hãy kiểm tra tác động và xác nhận xóa câu hỏi.',
        ]);
        $question = $action->approve($this->actor(), $reviewRequest, $data['review_note'] ?? null);

        if ($question->trashed()) {
            return redirect()->route('admin.questions.index')->with('status', 'Đã duyệt yêu cầu xóa câu hỏi.');
        }

        if ($reviewRequest->action === QuestionReviewAction::Retire) {
            return redirect()->route('admin.questions.edit', $question)->with('status', 'Đã duyệt ngừng dùng. Câu ẩn khỏi ngân hàng mới.');
        }

        return redirect()->route('admin.questions.edit', $question)->with('status', 'Đã phê duyệt yêu cầu.');
    }

    public function reject(
        Request $request,
        QuestionReviewRequest $reviewRequest,
        ReviewQuestionChangeAction $action,
    ): RedirectResponse {
        $data = $request->validate(['review_note' => ['nullable', 'string', 'max:2000']]);
        $question = $action->reject($this->actor(), $reviewRequest, $data['review_note'] ?? null);

        return redirect()->route('admin.questions.edit', $question)->with('status', 'Đã từ chối yêu cầu.');
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
