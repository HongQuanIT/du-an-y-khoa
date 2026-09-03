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
        $this->authorizePermission(Permission::QuestionView);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'target' => (string) $request->query('target', ''),
            'category' => (string) $request->query('category', ''),
            'question_id' => $request->query('question_id'),
        ];

        $query = QuestionFeedback::query()
            ->with(['option:id,question_id,label,content', 'question:id,stem,difficulty,status', 'session:id,user_id', 'user:id,name,email'])
            ->latest();

        if ($filters['question_id']) {
            $query->where('question_id', (int) $filters['question_id']);
        }

        if ($filters['q'] !== '') {
            $keyword = $filters['q'];
            $query->where(function ($builder) use ($keyword): void {
                $builder->where('message', 'like', "%{$keyword}%")
                    ->orWhereHas('question', fn ($question) => $question->where('stem', 'like', "%{$keyword}%"))
                    ->orWhereHas('user', function ($user) use ($keyword): void {
                        $user->where('name', 'like', "%{$keyword}%")
                            ->orWhere('email', 'like', "%{$keyword}%");
                    });
            });
        }

        if (array_key_exists($filters['status'], QuestionFeedback::statusLabels())) {
            $query->where('status', $filters['status']);
        }

        if (array_key_exists($filters['target'], QuestionFeedback::targetLabels())) {
            $query->where('target', $filters['target']);
        }

        if (array_key_exists($filters['category'], QuestionFeedback::categoryLabels())) {
            $query->where('category', $filters['category']);
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
        $this->authorizePermission(Permission::QuestionUpdate);

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
