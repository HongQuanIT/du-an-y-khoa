<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use App\Support\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\QuestionBank\Actions\InstructorReviewQuestionAction;
use Modules\QuestionBank\Enums\InstructorReviewDecision;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Support\QuestionInstructorReviewCycle;

final class TeachQuestionReviewController extends Controller
{
    private const TAB_PENDING = 'pending';

    private const TAB_APPROVED = 'approved';

    private const TAB_REJECTED = 'rejected';

    public function __construct(
        private readonly QuestionInstructorReviewCycle $reviewCycle,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeReview();

        $tab = $request->string('tab')->toString();
        if (! in_array($tab, [self::TAB_PENDING, self::TAB_APPROVED, self::TAB_REJECTED], true)) {
            $tab = self::TAB_PENDING;
        }

        $actor = $this->actor();
        $query = Question::query()
            ->with([
                'creator:id,name,email',
                'instructor:id,name',
                'instructorSlot1:id,name',
                'instructorSlot2:id,name',
                'publisher:id,name',
                'lessons:id,name',
                'pendingReviewRequest.requester:id,name',
            ]);

        match ($tab) {
            self::TAB_APPROVED => $this->scopeApprovedBy($query, $actor),
            self::TAB_REJECTED => $this->scopeRejectedBy($query, $actor),
            default => $this->scopePendingFor($query, $actor),
        };

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

        $stats = [
            'pending' => $this->scopePendingFor(Question::query(), $actor)->count(),
            'approved' => $this->scopeApprovedBy(Question::query(), $actor)->count(),
            'rejected' => $this->scopeRejectedBy(Question::query(), $actor)->count(),
        ];

        return view('classroom::teach.questions.reviews.index', [
            'questions' => $questions,
            'stats' => $stats,
            'tab' => $tab,
            'hidePeerVotes' => $tab === self::TAB_PENDING,
        ]);
    }

    public function show(Question $question): View
    {
        $this->authorizeReview();
        abort_unless($this->canViewQuestion($question), 404);

        $question->load([
            'options' => fn ($query) => $query->orderBy('order'),
            'lessons:id,name',
            'creator:id,name,email',
            'instructor:id,name',
            'instructorSlot1:id,name',
            'instructorSlot2:id,name',
            'publisher:id,name',
            'pendingReviewRequest.requester:id,name',
        ]);

        $actor = $this->actor();
        $canDecide = $question->status === QuestionStatus::InReview
            && (int) $question->created_by !== (int) $actor->getKey()
            && ! $this->reviewCycle->actorHasDecided($question, $actor);

        return view('classroom::teach.questions.reviews.show', [
            'question' => $question,
            'canDecide' => $canDecide,
            'hidePeerVotes' => $canDecide,
            'approvalCount' => $this->reviewCycle->approvedCountFromSlots($question),
        ]);
    }

    public function approve(
        Request $request,
        Question $question,
        InstructorReviewQuestionAction $action,
    ): RedirectResponse {
        $this->authorizeReview();

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $question = $action->approve($this->actor(), $question, $data['review_note'] ?? null);

        $enough = $question->status === QuestionStatus::PendingPublish;

        return redirect()
            ->route('teach.questions.reviews.index', ['tab' => self::TAB_APPROVED])
            ->with('status', $enough
                ? 'Đã đủ 2 giảng viên chấp nhận. Câu chuyển sang chờ Admin xuất bản.'
                : 'Đã ghi nhận phiếu của bạn (1/2). Cần thêm 1 giảng viên khác.');
    }

    public function reject(
        Request $request,
        Question $question,
        InstructorReviewQuestionAction $action,
    ): RedirectResponse {
        $this->authorizeReview();

        $data = $request->validate([
            'review_note' => ['required', 'string', 'max:2000'],
        ], [
            'review_note.required' => 'Vui lòng nhập lý do từ chối.',
        ]);

        $action->reject($this->actor(), $question, $data['review_note']);

        return redirect()
            ->route('teach.questions.reviews.index', ['tab' => self::TAB_REJECTED])
            ->with('status', 'Đã từ chối câu hỏi. Một phiếu từ chối là đủ để trả về Content Creator.');
    }

    /**
     * @param  Builder<Question>  $query
     * @return Builder<Question>
     */
    private function scopePendingFor($query, User $actor)
    {
        $actorId = (int) $actor->getKey();

        return $query
            ->where('status', QuestionStatus::InReview->value)
            ->where(function ($builder) use ($actorId): void {
                $builder
                    ->whereNull('created_by')
                    ->orWhere('created_by', '!=', $actorId);
            })
            ->where(function ($builder) use ($actorId): void {
                $builder
                    ->where(function ($inner) use ($actorId): void {
                        $inner->whereNull('instructor_1_id')
                            ->orWhere('instructor_1_id', '!=', $actorId);
                    })
                    ->where(function ($inner) use ($actorId): void {
                        $inner->whereNull('instructor_2_id')
                            ->orWhere('instructor_2_id', '!=', $actorId);
                    });
            });
    }

    /**
     * @param  Builder<Question>  $query
     * @return Builder<Question>
     */
    private function scopeApprovedBy($query, User $actor)
    {
        $actorId = (int) $actor->getKey();

        return $query->where(function ($builder) use ($actorId): void {
            $builder
                ->where(function ($inner) use ($actorId): void {
                    $inner->where('instructor_1_id', $actorId)
                        ->where('instructor_1_decision', InstructorReviewDecision::Approved->value);
                })
                ->orWhere(function ($inner) use ($actorId): void {
                    $inner->where('instructor_2_id', $actorId)
                        ->where('instructor_2_decision', InstructorReviewDecision::Approved->value);
                })
                ->orWhere(function ($inner) use ($actorId): void {
                    $inner->where('instructor_id', $actorId)
                        ->whereIn('status', [
                            QuestionStatus::PendingPublish->value,
                            QuestionStatus::Published->value,
                        ]);
                });
        });
    }

    /**
     * @param  Builder<Question>  $query
     * @return Builder<Question>
     */
    private function scopeRejectedBy($query, User $actor)
    {
        $actorId = (int) $actor->getKey();

        return $query
            ->where('status', QuestionStatus::Rejected->value)
            ->where('rejected_by_role', Role::Instructor->value)
            ->where(function ($builder) use ($actorId): void {
                $builder
                    ->where('instructor_id', $actorId)
                    ->orWhere(function ($inner) use ($actorId): void {
                        $inner->where('instructor_1_id', $actorId)
                            ->where('instructor_1_decision', InstructorReviewDecision::Rejected->value);
                    })
                    ->orWhere(function ($inner) use ($actorId): void {
                        $inner->where('instructor_2_id', $actorId)
                            ->where('instructor_2_decision', InstructorReviewDecision::Rejected->value);
                    });
            });
    }

    private function canViewQuestion(Question $question): bool
    {
        if ($question->status === QuestionStatus::InReview) {
            return true;
        }

        $actor = $this->actor();

        return $this->reviewCycle->actorHasDecided($question, $actor)
            || (
                (int) $question->instructor_id === (int) $actor->getKey()
                && in_array($question->status, [
                    QuestionStatus::PendingPublish,
                    QuestionStatus::Published,
                    QuestionStatus::Rejected,
                ], true)
            );
    }

    private function authorizeReview(): void
    {
        abort_unless(
            $this->actor()->hasRole(Role::Instructor->value)
            && $this->actor()->can(Permission::QuestionReview->value),
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
