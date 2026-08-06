<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Modules\QuestionBank\Actions\RepeatQuestionSessionAction;
use Modules\QuestionBank\Http\Requests\RenameQuestionSessionRequest;
use Modules\QuestionBank\Http\Requests\RepeatQuestionSessionRequest;
use Modules\QuestionBank\Models\QuestionSession;
use RuntimeException;

final class SessionHistoryController extends Controller
{
    public function __construct(private readonly RepeatQuestionSessionAction $repeatSession) {}

    public function rename(RenameQuestionSessionRequest $request, QuestionSession $session): RedirectResponse
    {
        $this->authorize('update', $session);
        $filters = $session->filters ?? [];
        $filters['name'] = trim((string) $request->validated('name'));
        $session->forceFill(['filters' => $filters])->save();

        return back()->with('status', 'Đã đổi tên phiên luyện.');
    }

    public function repeat(RepeatQuestionSessionRequest $request, QuestionSession $session): RedirectResponse
    {
        $this->authorize('view', $session);

        try {
            $repeated = $this->repeatSession->handle(
                $request->user(),
                $session,
                array_values(array_map('strval', $request->validated('repeat_statuses'))),
                (int) $request->validated('question_count'),
            );
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['repeat_statuses' => $exception->getMessage()]);
        }

        return redirect()
            ->route('qbank.session', $repeated)
            ->with('status', 'Đã tạo phiên làm lại.');
    }

    public function destroy(QuestionSession $session): RedirectResponse
    {
        $this->authorize('delete', $session);
        $session->delete();

        return redirect()->route('qbank.index')->with('status', 'Đã xoá phiên luyện.');
    }
}
