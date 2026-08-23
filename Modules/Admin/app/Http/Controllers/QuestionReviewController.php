<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Admin\Actions\ReviewQuestionChangeAction;
use Modules\Admin\Support\QuestionAccess;
use Modules\QuestionBank\Models\QuestionReviewRequest;
use Modules\QuestionBank\Models\Topic;

final class QuestionReviewController extends Controller
{
    public function show(QuestionReviewRequest $reviewRequest): View
    {
        abort_unless(QuestionAccess::isReviewer($this->actor()), 403);

        $reviewRequest->load(['question.options', 'question.topics:id,name', 'requester:id,name,email']);
        $topicNames = Topic::query()
            ->whereIn('id', array_map('intval', (array) ($reviewRequest->payload['topic_ids'] ?? [])))
            ->pluck('name', 'id');

        return view('admin::questions.review', compact('reviewRequest', 'topicNames'));
    }

    public function approve(
        Request $request,
        QuestionReviewRequest $reviewRequest,
        ReviewQuestionChangeAction $action,
    ): RedirectResponse {
        $data = $request->validate(['review_note' => ['nullable', 'string', 'max:2000']]);
        $question = $action->approve($this->actor(), $reviewRequest, $data['review_note'] ?? null);

        if ($question->trashed()) {
            return redirect()->route('admin.questions.index')->with('status', 'Đã duyệt yêu cầu xóa câu hỏi.');
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
