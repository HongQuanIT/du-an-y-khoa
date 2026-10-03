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
use Modules\QuestionBank\Actions\ChangeReviewerFlagInConflictAction;
use Modules\QuestionBank\Actions\FlagQuestionReviewAction;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\ReviewerFlag;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionFlagChangeEvent;
use Modules\QuestionBank\Support\QuestionReviewerFlagCycle;
use Modules\QuestionBank\Support\ReviewerChecklist;

final class ReviewerFlagController extends Controller
{
    public function __construct(private readonly QuestionReviewerFlagCycle $flagCycle) {}

    public function index(Request $request): View
    {
        $actor = $request->user();
        $tab = $request->string('tab')->toString();
        if (! in_array($tab, ['pending', 'warning', 'done'], true)) {
            $tab = 'pending';
        }

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
                'warning' => $this->scopeForTab('warning', $actor)->count(),
                'done' => $this->scopeForTab('done', $actor)->count(),
            ],
        ]);
    }

    public function show(Request $request, Question $question): View
    {
        $actor = $request->user();

        if (in_array($question->status, [
            QuestionStatus::Published,
            QuestionStatus::Private,
            QuestionStatus::Retired,
        ], true)) {
            redirect()
                ->route('reviewer.questions.flags.index', ['tab' => 'done'])
                ->with('status', 'Câu hỏi đã được xuất bản và không còn trong hàng đợi review.')
                ->throwResponse();
        }

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

        $ownFlag = $this->flagCycle->actorFlag($question, $actor);
        $inConflict = $question->status === QuestionStatus::FlagConflict && $ownFlag !== null;
        $hasFlagPermission = $actor->can('question.flag');

        return view('reviewer::questions.show', [
            'question' => $question,
            'canFlag' => $this->canFlag($question, $actor),
            'canChangeInConflict' => $inConflict && $hasFlagPermission,
            'inConflict' => $inConflict,
            'hasFlagPermission' => $hasFlagPermission,
            'flags' => ReviewerFlag::cases(),
            'ownFlag' => $ownFlag,
            'ownNote' => $this->flagCycle->actorNote($question, $actor),
            'ownFailedChecks' => $this->flagCycle->actorFailedChecks($question, $actor),
            'peerFlag' => $this->flagCycle->peerFlag($question, $actor)?->value,
            'ackText' => QuestionFlagChangeEvent::ACK_TEXT,
        ]);
    }

    public function store(Request $request, Question $question, FlagQuestionReviewAction $action): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->can('question_flag.view') && $this->canFlag($question, $actor), 403);

        $data = $request->validate([
            'flag' => ['required', 'string', Rule::in(ReviewerFlag::values())],
            'note' => ['nullable', 'string', 'max:2000'],
            'failed_checks' => [
                Rule::excludeIf(fn (): bool => $request->input('flag') !== ReviewerFlag::Red->value),
                'required',
                'array',
                'min:1',
                'max:20',
            ],
            'failed_checks.*' => ['string', Rule::in(ReviewerChecklist::keys())],
        ], [
            'failed_checks.required' => 'Không đạt cần đánh dấu ít nhất một mục checklist.',
            'failed_checks.min' => 'Không đạt cần đánh dấu ít nhất một mục checklist.',
            'failed_checks.*.in' => 'Mục checklist không hợp lệ.',
        ]);

        $action->handle(
            $actor,
            $question,
            ReviewerFlag::from($data['flag']),
            $data['note'] ?? null,
            ReviewerChecklist::only((array) ($data['failed_checks'] ?? [])),
        );

        return redirect()->route('reviewer.questions.flags.show', $question->fresh())
            ->with('status', 'Đã ghi nhận cờ của bạn.');
    }

    public function update(
        Request $request,
        Question $question,
        ChangeReviewerFlagInConflictAction $action,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor->can('question.flag') && $this->visibleTo($question, $actor), 403);

        $data = $request->validate([
            'flag' => ['required', 'string', Rule::in(ReviewerFlag::values())],
            'note' => ['nullable', 'string', 'max:2000'],
            'responsibility_acked' => [
                Rule::excludeIf(fn (): bool => ! $this->submittedFlagDiffers($request, $question)),
                'accepted',
            ],
            'failed_checks' => [
                Rule::excludeIf(fn (): bool => $request->input('flag') !== ReviewerFlag::Red->value),
                'required',
                'array',
                'min:1',
                'max:20',
            ],
            'failed_checks.*' => ['string', Rule::in(ReviewerChecklist::keys())],
        ], [
            'responsibility_acked.accepted' => 'Bạn phải xác nhận chịu trách nhiệm trước khi đổi cờ.',
            'failed_checks.required' => 'Không đạt cần đánh dấu ít nhất một mục checklist.',
            'failed_checks.min' => 'Không đạt cần đánh dấu ít nhất một mục checklist.',
            'failed_checks.*.in' => 'Mục checklist không hợp lệ.',
        ]);

        $toFlag = ReviewerFlag::from($data['flag']);
        $ownFlag = $this->flagCycle->actorFlag($question, $actor);
        $isChange = $ownFlag !== null && $ownFlag !== $toFlag;

        $result = $action->handle(
            $actor,
            $question,
            $toFlag,
            $data['note'] ?? null,
            $isChange && $request->boolean('responsibility_acked'),
            ReviewerChecklist::only((array) ($data['failed_checks'] ?? [])),
        );

        $message = match ($result->status) {
            QuestionStatus::PendingPublish => 'Hai cờ xanh — câu đã vào hàng đợi xuất bản.',
            QuestionStatus::Rejected => 'Hai cờ đỏ — hệ thống đã trả về biên tập.',
            default => 'Đã ghi nhận. Câu vẫn ở Cảnh báo vì hai cờ vẫn khác nhau.',
        };

        return redirect()
            ->route('reviewer.questions.flags.show', $question->fresh())
            ->with('status', $message);
    }

    private function submittedFlagDiffers(Request $request, Question $question): bool
    {
        $own = $this->flagCycle->actorFlag($question, $request->user());
        $submitted = ReviewerFlag::tryFrom((string) $request->input('flag'));

        return $own !== null && $submitted !== null && $own !== $submitted;
    }

    private function visibleTo(Question $question, User $actor): bool
    {
        return $this->scopeForTab('pending', $actor)->whereKey($question->getKey())->exists()
            || $this->scopeForTab('warning', $actor)->whereKey($question->getKey())->exists()
            || $this->scopeForTab('done', $actor)->whereKey($question->getKey())->exists();
    }

    private function canFlag(Question $question, User $actor): bool
    {
        return $actor->can('question.flag')
            && $question->status === QuestionStatus::InFlagReview
            && (int) $question->created_by !== (int) $actor->getKey()
            && ! $this->flagCycle->actorHasFlagged($question, $actor)
            && (
                ! $question->hasStickyReviewers()
                || $this->flagCycle->actorIsStickyReviewer($question, $actor)
            );
    }

    /** @return Builder<Question> */
    private function scopeForTab(string $tab, User $actor): Builder
    {
        $actorId = (int) $actor->getKey();
        $query = Question::query();

        if ($tab === 'warning') {
            return $query
                ->where('status', QuestionStatus::FlagConflict->value)
                ->where(fn (Builder $builder) => $builder
                    ->where('reviewer_1_id', $actorId)
                    ->orWhere('reviewer_2_id', $actorId));
        }

        if ($tab === 'done') {
            return $query
                ->whereNotIn('status', [
                    QuestionStatus::FlagConflict->value,
                    QuestionStatus::Published->value,
                    QuestionStatus::Private->value,
                    QuestionStatus::Retired->value,
                ])
                ->where(fn (Builder $builder) => $builder
                    ->where('reviewer_1_id', $actorId)
                    ->orWhere('reviewer_2_id', $actorId));
        }

        return $query
            ->where('status', QuestionStatus::InFlagReview->value)
            ->where(fn (Builder $builder) => $builder->whereNull('created_by')->orWhere('created_by', '!=', $actorId))
            ->where(function (Builder $builder) use ($actorId): void {
                $builder->where(function (Builder $sticky) use ($actorId): void {
                    $sticky->whereNotNull('sticky_reviewer_1_id')
                        ->where(fn (Builder $ids) => $ids
                            ->where('sticky_reviewer_1_id', $actorId)
                            ->orWhere('sticky_reviewer_2_id', $actorId));
                })->orWhere(function (Builder $open) use ($actorId): void {
                    $open->whereNull('sticky_reviewer_1_id')
                        ->whereNull('sticky_reviewer_2_id')
                        ->where(fn (Builder $slots) => $slots
                            ->where(fn (Builder $inner) => $inner->whereNull('reviewer_1_id')->orWhere('reviewer_1_id', '!=', $actorId))
                            ->where(fn (Builder $inner) => $inner->whereNull('reviewer_2_id')->orWhere('reviewer_2_id', '!=', $actorId)));
                });
            })
            ->where(fn (Builder $builder) => $builder
                ->where(fn (Builder $inner) => $inner->whereNull('reviewer_1_id')->orWhere('reviewer_1_id', '!=', $actorId))
                ->where(fn (Builder $inner) => $inner->whereNull('reviewer_2_id')->orWhere('reviewer_2_id', '!=', $actorId)));
    }
}
