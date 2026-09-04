<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Admin\Support\QuestionAccess;
use Modules\QuestionBank\Actions\FindSimilarQuestionsAction;
use Modules\QuestionBank\Enums\DuplicateSeverity;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionSimilarityMatch;

/**
 * Per-question duplicate check — dedicated detail page.
 */
final class QuestionDuplicateController extends Controller
{
    public function show(Request $request, Question $question, FindSimilarQuestionsAction $action): View
    {
        $this->authorizePermission(Permission::QuestionView);
        QuestionAccess::authorizeView($this->actor(), $question);

        $question->load([
            'options' => fn ($q) => $q->orderBy('order'),
            'creator:id,name',
        ]);

        $shouldRefresh = $request->boolean('refresh')
            || $question->similarity_checked_at === null;

        if ($shouldRefresh) {
            $action->refreshFor($question);
            $question->refresh();
        }

        $matches = $action->matchesFor($question, 100);

        $rows = $matches->map(function (QuestionSimilarityMatch $match) use ($question): ?array {
            $otherId = $match->otherQuestionId((string) $question->getKey());
            $other = $match->question_id_low === $otherId
                ? $match->questionLow
                : $match->questionHigh;

            if ($other === null) {
                return null;
            }

            return [
                'match' => $match,
                'other' => $other,
            ];
        })->filter()->values();

        $kpi = [
            'total' => $rows->count(),
            'exact' => $rows->filter(fn (array $r) => $r['match']->severity === DuplicateSeverity::Exact)->count(),
            'very_high' => $rows->filter(fn (array $r) => $r['match']->severity === DuplicateSeverity::VeryHigh)->count(),
            'high' => $rows->filter(fn (array $r) => $r['match']->severity === DuplicateSeverity::High)->count(),
            'medium' => $rows->filter(fn (array $r) => $r['match']->severity === DuplicateSeverity::Medium)->count(),
            'low' => $rows->filter(fn (array $r) => $r['match']->severity === DuplicateSeverity::Low)->count(),
        ];

        return view('admin::questions.duplicates-check', [
            'question' => $question,
            'rows' => $rows,
            'kpi' => $kpi,
            'threshold' => DuplicateSeverity::DISPLAY_THRESHOLD,
        ]);
    }

    public function check(Question $question, FindSimilarQuestionsAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::QuestionView);
        QuestionAccess::authorizeView($this->actor(), $question);

        $question->load('options');
        $matches = $action->refreshFor($question);
        $count = $matches->count();
        $threshold = (int) DuplicateSeverity::DISPLAY_THRESHOLD;

        return redirect()
            ->route('admin.questions.duplicates.show', $question)
            ->with(
                'status',
                $count === 0
                    ? "Không tìm thấy câu ≥{$threshold}% trùng trong ngân hàng."
                    : "Tìm thấy {$count} câu ≥{$threshold}% trùng / gần trùng.",
            );
    }

    private function authorizePermission(Permission $permission): void
    {
        abort_unless($this->actor()->can($permission->value), 403);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
