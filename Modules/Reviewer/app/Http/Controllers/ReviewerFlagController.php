<?php

declare(strict_types=1);

namespace Modules\Reviewer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\QuestionBank\Actions\FlagQuestionReviewAction;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\ReviewerFlag;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Support\QuestionReviewerFlagCycle;

final class ReviewerFlagController extends Controller
{
    public function __construct(private readonly QuestionReviewerFlagCycle $flagCycle) {}

    public function index(Request $request): View
    {
        $actor = $request->user();
        $tab = $request->query('tab') === 'done' ? 'done' : 'pending';
        $query = $this->scopeForTab($tab, $actor)->with(['lessons:id,name', 'assignedInstructor:id,name']);

        if ($request->filled('q')) {
            $term = trim((string) $request->query('q'));
            $query->where(fn (Builder $builder) => $builder
                ->where('code', 'like', "%{$term}%")
                ->orWhere('stem', 'like', "%{$term}%"));
        }

        return view('reviewer::questions.index', [
            'questions' => $query->latest('updated_at')->paginate(20)->withQueryString(),
            'tab' => $tab,
            'stats' => [
                'pending' => $this->scopeForTab('pending', $actor)->count(),
                'done' => $this->scopeForTab('done', $actor)->count(),
            ],
        ]);
    }

    public function show(Request $request, Question $question): View
    {
        $actor = $request->user();
        abort_unless($this->visibleTo($question, $actor), 403);

        $question->load([
            'options' => fn ($query) => $query->orderBy('order'),
            'hints' => fn ($query) => $query->orderBy('sort_order'),
            'lessons:id,name',
            'creator:id,name',
            'assignedInstructor:id,name',
            'reviewerSlot1:id,name',
            'reviewerSlot2:id,name',
        ]);

        return view('reviewer::questions.show', [
            'question' => $question,
            'canFlag' => $this->canFlag($question, $actor),
            'flags' => ReviewerFlag::cases(),
            'ownFlag' => $this->flagCycle->actorFlag($question, $actor),
        ]);
    }

    public function store(Request $request, Question $question, FlagQuestionReviewAction $action): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->can('question_flag.view') && $this->canFlag($question, $actor), 403);

        $data = $request->validate([
            'flag' => ['required', 'string', Rule::in(ReviewerFlag::values())],
            'note' => [Rule::requiredIf(fn (): bool => $request->input('flag') === ReviewerFlag::Red->value), 'nullable', 'string', 'max:2000'],
        ]);

        $action->handle($actor, $question, ReviewerFlag::from($data['flag']), $data['note'] ?? null);

        return redirect()->route('reviewer.questions.flags.index', ['tab' => 'done'])
            ->with('status', 'Đã ghi nhận cờ của bạn.');
    }

    private function visibleTo(Question $question, User $actor): bool
    {
        return $this->scopeForTab('pending', $actor)->whereKey($question->getKey())->exists()
            || $this->scopeForTab('done', $actor)->whereKey($question->getKey())->exists();
    }

    private function canFlag(Question $question, User $actor): bool
    {
        return $actor->can('question.flag')
            && $question->status === QuestionStatus::InFlagReview
            && (int) $question->created_by !== (int) $actor->getKey()
            && ! $this->flagCycle->actorHasFlagged($question, $actor);
    }

    /** @return Builder<Question> */
    private function scopeForTab(string $tab, User $actor): Builder
    {
        $actorId = (int) $actor->getKey();
        $query = Question::query();

        if ($tab === 'done') {
            return $query->where(fn (Builder $builder) => $builder
                ->where('reviewer_1_id', $actorId)->orWhere('reviewer_2_id', $actorId));
        }

        return $query->where('status', QuestionStatus::InFlagReview->value)
            ->where(fn (Builder $builder) => $builder->whereNull('created_by')->orWhere('created_by', '!=', $actorId))
            ->where(fn (Builder $builder) => $builder->whereNull('reviewer_1_id')->orWhere('reviewer_1_id', '!=', $actorId))
            ->where(fn (Builder $builder) => $builder->whereNull('reviewer_2_id')->orWhere('reviewer_2_id', '!=', $actorId));
    }
}
