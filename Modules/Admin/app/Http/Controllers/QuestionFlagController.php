<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use App\Support\Enums\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Admin\Support\QuestionAccess;
use Modules\QuestionBank\Actions\FlagQuestionReviewAction;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\ReviewerFlag;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Support\QuestionReviewerFlagCycle;

final class QuestionFlagController extends Controller
{
    public function __construct(
        private readonly QuestionReviewerFlagCycle $flagCycle,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeFlagView();

        $tab = $request->string('tab')->toString();
        if (! in_array($tab, ['pending', 'done'], true)) {
            $tab = 'pending';
        }

        $actor = $this->actor();
        $query = Question::query()->with([
            'creator:id,name',
            'assignedInstructor:id,name',
            'lessons:id,name',
        ]);

        if ($tab === 'done') {
            $this->scopeFlaggedBy($query, $actor);
        } else {
            $this->scopePendingFor($query, $actor);
        }

        $query->latest('updated_at');

        if ($request->filled('q')) {
            $term = trim((string) $request->string('q'));
            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('code', 'like', "%{$term}%")
                    ->orWhere('stem', 'like', "%{$term}%");
            });
        }

        $questions = $query->paginate(20)->withQueryString();

        return view('admin::questions.flags.index', [
            'questions' => $questions,
            'tab' => $tab,
            'stats' => [
                'pending' => $this->scopePendingFor(Question::query(), $actor)->count(),
                'done' => $this->scopeFlaggedBy(Question::query(), $actor)->count(),
            ],
        ]);
    }

    public function show(Question $question): View
    {
        $this->authorizeFlagView();
        QuestionAccess::authorizeView($this->actor(), $question);

        $question->load([
            'options' => fn ($query) => $query->orderBy('order'),
            'hints' => fn ($query) => $query->orderBy('sort_order'),
            'lessons:id,name',
            'creator:id,name',
            'assignedInstructor:id,name',
            'reviewerSlot1:id,name',
            'reviewerSlot2:id,name',
        ]);

        $actor = $this->actor();
        $canFlag = $question->status === QuestionStatus::InFlagReview
            && (int) $question->created_by !== (int) $actor->getKey()
            && ! $this->flagCycle->actorHasFlagged($question, $actor);

        return view('admin::questions.flags.show', [
            'question' => $question,
            'canFlag' => $canFlag,
            'ownFlag' => $this->flagCycle->actorFlag($question, $actor),
            'flags' => ReviewerFlag::cases(),
        ]);
    }

    public function store(
        Request $request,
        Question $question,
        FlagQuestionReviewAction $action,
    ): RedirectResponse {
        $this->authorizeFlag();
        QuestionAccess::authorizeView($this->actor(), $question);

        $data = $request->validate([
            'flag' => ['required', 'string', Rule::in(ReviewerFlag::values())],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $action->handle(
            $this->actor(),
            $question,
            ReviewerFlag::from($data['flag']),
            $data['note'] ?? null,
        );

        return redirect()
            ->route('admin.questions.flags.index', ['tab' => 'done'])
            ->with('status', 'Đã ghi nhận cờ của bạn.');
    }

    private function scopePendingFor($query, User $actor)
    {
        $actorId = (int) $actor->getKey();

        return $query
            ->where('status', QuestionStatus::InFlagReview->value)
            ->where(function ($builder) use ($actorId): void {
                $builder
                    ->whereNull('created_by')
                    ->orWhere('created_by', '!=', $actorId);
            })
            ->where(function ($builder) use ($actorId): void {
                $builder
                    ->where(function ($inner) use ($actorId): void {
                        $inner->whereNull('reviewer_1_id')
                            ->orWhere('reviewer_1_id', '!=', $actorId);
                    })
                    ->where(function ($inner) use ($actorId): void {
                        $inner->whereNull('reviewer_2_id')
                            ->orWhere('reviewer_2_id', '!=', $actorId);
                    });
            });
    }

    private function scopeFlaggedBy($query, User $actor)
    {
        $actorId = (int) $actor->getKey();

        return $query->where(function ($builder) use ($actorId): void {
            $builder
                ->where('reviewer_1_id', $actorId)
                ->orWhere('reviewer_2_id', $actorId);
        });
    }

    private function authorizeFlag(): void
    {
        abort_unless(
            $this->actor()->hasRole(Role::Reviewer->value)
            && $this->actor()->can('question_flag.view')
            && $this->actor()->can(Permission::QuestionFlag->value),
            403,
        );
    }

    private function authorizeFlagView(): void
    {
        abort_unless(
            $this->actor()->hasRole(Role::Reviewer->value)
            && $this->actor()->can('question_flag.view'),
            403,
        );
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
