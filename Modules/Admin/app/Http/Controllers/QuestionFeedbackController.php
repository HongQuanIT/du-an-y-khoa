<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\QuestionBank\Models\QuestionFeedback;

final class QuestionFeedbackController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(
            $this->actor()->can('question_feedback.view'),
            403,
        );

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => array_values(array_intersect(
                array_map('strval', (array) $request->query('status', [])),
                array_keys(QuestionFeedback::statusLabels()),
            )),
            'target' => array_values(array_intersect(
                array_map('strval', (array) $request->query('target', [])),
                array_keys(QuestionFeedback::targetLabels()),
            )),
            'category' => array_values(array_intersect(
                array_map('strval', (array) $request->query('category', [])),
                array_keys(QuestionFeedback::categoryLabels()),
            )),
            'question_id' => $request->query('question_id'),
        ];

        $query = QuestionFeedback::query()
            ->with(['option:id,question_id,label,content', 'question:id,stem,difficulty,status', 'session:id,user_id', 'user:id,name,email'])
            ->latest();

        if ($filters['question_id']) {
            $query->where('question_id', (int) $filters['question_id']);
        }

        if ($filters['q'] !== '') {
            $keyword = '%'.addcslashes($filters['q'], '\\%_').'%';
            $query->where(function ($builder) use ($keyword): void {
                $builder->where('message', 'like', $keyword)
                    ->orWhereHas('question', fn ($question) => $question->where('stem', 'like', $keyword))
                    ->orWhereHas('user', function ($user) use ($keyword): void {
                        $user->where('name', 'like', $keyword)
                            ->orWhere('email', 'like', $keyword);
                    });
            });
        }

        if ($filters['status'] !== []) {
            $query->whereIn('status', $filters['status']);
        }

        if ($filters['target'] !== []) {
            $query->whereIn('target', $filters['target']);
        }

        if ($filters['category'] !== []) {
            $query->whereIn('category', $filters['category']);
        }

        return view('admin::question-feedback.index', [
            'feedbackItems' => $query->paginate(20)->withQueryString(),
            'filters' => $filters,
            'statuses' => QuestionFeedback::statusLabels(),
            'targets' => QuestionFeedback::targetLabels(),
            'categories' => QuestionFeedback::categoryLabels(),
            'statusCounts' => QuestionFeedback::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->map(fn ($count): int => (int) $count),
        ]);
    }

    public function updateStatus(Request $request, QuestionFeedback $feedback): RedirectResponse
    {
        abort_unless($this->actor()->canAny(['question_feedback.update']), 403);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys(QuestionFeedback::statusLabels()))],
        ]);

        $before = ['status' => $feedback->status];
        $feedback->forceFill(['status' => $validated['status']])->save();

        Auditor::record(
            'admin.question_feedback.status_changed',
            actor: $request->user(),
            auditable: $feedback->question,
            before: $before,
            after: ['status' => $feedback->status],
            request: $request,
            metadata: [
                'question_feedback_id' => $feedback->getKey(),
                'question_id' => $feedback->question_id,
                'target' => $feedback->target,
                'category' => $feedback->category,
            ],
        );

        return back()->with('status', 'Đã cập nhật trạng thái feedback.');
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
