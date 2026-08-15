<?php

declare(strict_types=1);

namespace Modules\Exam\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Services\QuestionSessionInsights;

final class ExamSessionReviewController extends Controller
{
    public function __construct(private readonly QuestionSessionInsights $insights) {}

    public function __invoke(Request $request, QuestionSession $session): View|RedirectResponse
    {
        $this->authorize('view', $session);

        if ($session->status !== SessionStatus::Completed) {
            if (($session->question_ids ?? []) === []) {
                return redirect()
                    ->route('exam.index')
                    ->with('status', 'Phiên này không còn câu hỏi để ôn. Hãy tạo phiên mới.');
            }

            return redirect()->route('exam.session', $session);
        }

        $items = $this->insights->reviewItems($session);
        $allowed = ['all', 'correct', 'wrong', 'skipped', 'flagged', 'needs'];
        $filter = in_array($request->query('filter'), $allowed, true)
            ? (string) $request->query('filter')
            : 'all';

        return view('exam::review', [
            'session' => $session,
            'items' => $items,
            'initialFilter' => $filter,
        ]);
    }
}
