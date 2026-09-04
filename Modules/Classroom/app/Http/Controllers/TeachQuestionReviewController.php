<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use App\Support\Enums\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\QuestionBank\Actions\InstructorReviewQuestionAction;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;

final class TeachQuestionReviewController extends Controller
{
    private const TAB_PENDING = 'pending';

    private const TAB_APPROVED = 'approved';

    private const TAB_REJECTED = 'rejected';

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
                'publisher:id,name',
                'medicalTaxonomyNodes:id,name',
                'pendingReviewRequest.requester:id,name',
            ]);

        match ($tab) {
            self::TAB_APPROVED => $query
                ->where('instructor_id', $actor->getKey())
                ->whereIn('status', [
                    QuestionStatus::PendingPublish->value,
                    QuestionStatus::Published->value,
                ]),
            self::TAB_REJECTED => $query
                ->where('instructor_id', $actor->getKey())
                ->where('status', QuestionStatus::Rejected->value)
                ->where('rejected_by_role', Role::Instructor->value),
            default => $query->where('status', QuestionStatus::InReview->value),
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
            'pending' => Question::query()->where('status', QuestionStatus::InReview->value)->count(),
            'approved' => Question::query()
                ->where('instructor_id', $actor->getKey())
                ->whereIn('status', [
                    QuestionStatus::PendingPublish->value,
                    QuestionStatus::Published->value,
                ])
                ->count(),
            'rejected' => Question::query()
                ->where('instructor_id', $actor->getKey())
                ->where('status', QuestionStatus::Rejected->value)
                ->where('rejected_by_role', Role::Instructor->value)
                ->count(),
        ];

        return view('classroom::teach.questions.reviews.index', [
            'questions' => $questions,
            'stats' => $stats,
            'tab' => $tab,
        ]);
    }

    public function show(Question $question): View
    {
        $this->authorizeReview();
        abort_unless($this->canViewQuestion($question), 404);

        $question->load([
            'options' => fn ($query) => $query->orderBy('order'),
            'medicalTaxonomyNodes:id,name',
            'creator:id,name,email',
            'instructor:id,name',
            'publisher:id,name',
            'pendingReviewRequest.requester:id,name',
        ]);

        $canDecide = $question->status === QuestionStatus::InReview;

        return view('classroom::teach.questions.reviews.show', compact('question', 'canDecide'));
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

        $action->approve($this->actor(), $question, $data['review_note'] ?? null);

        return redirect()
            ->route('teach.questions.reviews.index', ['tab' => self::TAB_APPROVED])
            ->with('status', 'Đã duyệt câu hỏi. Câu chuyển sang chờ Admin xuất bản.');
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
            ->with('status', 'Đã từ chối câu hỏi và gửi lại cho Content Creator.');
    }

    private function canViewQuestion(Question $question): bool
    {
        if ($question->status === QuestionStatus::InReview) {
            return true;
        }

        return (int) $question->instructor_id === (int) $this->actor()->getKey()
            && in_array($question->status, [
                QuestionStatus::PendingPublish,
                QuestionStatus::Published,
                QuestionStatus::Rejected,
            ], true);
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
