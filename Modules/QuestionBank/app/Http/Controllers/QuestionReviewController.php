<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Services\QuestionSessionInsights;

final class QuestionReviewController extends Controller
{
    public function __construct(private readonly QuestionSessionInsights $insights) {}

    public function __invoke(Request $request, QuestionSession $session): View|RedirectResponse
    {
        $this->authorize('view', $session);

        if ($session->status !== SessionStatus::Completed) {
            return redirect()->route('qbank.session', $session);
        }

        $items = $this->insights->reviewItems($session);
        $allowed = ['all', 'correct', 'wrong', 'skipped', 'flagged', 'needs'];
        $filter = in_array($request->query('filter'), $allowed, true)
            ? (string) $request->query('filter')
            : 'all';

        return view('questionbank::review-live', [
            'session' => $session,
            'items' => $items,
            'initialFilter' => $filter,
        ]);
    }
}
