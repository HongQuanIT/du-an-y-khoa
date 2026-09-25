<?php

declare(strict_types=1);

namespace Modules\Reviewer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;

final class ReviewerDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $actorId = (int) $request->user()->getKey();
        $pending = Question::query()
            ->where('status', QuestionStatus::InFlagReview->value)
            ->where(fn ($query) => $query->whereNull('created_by')->orWhere('created_by', '!=', $actorId))
            ->where(fn ($query) => $query->whereNull('reviewer_1_id')->orWhere('reviewer_1_id', '!=', $actorId))
            ->where(fn ($query) => $query->whereNull('reviewer_2_id')->orWhere('reviewer_2_id', '!=', $actorId));
        $done = Question::query()->where(fn ($query) => $query
            ->where('reviewer_1_id', $actorId)->orWhere('reviewer_2_id', $actorId));

        return view('reviewer::dashboard', [
            'pendingCount' => (clone $pending)->count(),
            'doneCount' => $done->count(),
            'questions' => $pending->latest('updated_at')->limit(5)->get(),
        ]);
    }
}
