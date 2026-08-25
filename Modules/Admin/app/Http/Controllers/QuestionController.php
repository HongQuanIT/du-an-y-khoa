<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Admin\Actions\RequestQuestionDeletionAction;
use Modules\Admin\Actions\CloneQuestionAction;
use Modules\Admin\Actions\SaveAdminQuestionAction;
use Modules\Admin\Actions\TransitionQuestionStatusAction;
use Modules\Admin\Support\QuestionAccess;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionReviewAction;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;

final class QuestionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::QuestionView);

        $actor = $this->actor();
        $query = QuestionAccess::scopeVisibleTo(
            Question::query()->with(['medicalTaxonomyNodes', 'creator:id,name', 'pendingReviewRequest.requester:id,name']),
            $actor,
        )->latest('updated_at');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('stem', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', (string) $status);
        }

        if ($difficulty = $request->query('difficulty')) {
            $query->where('difficulty', (string) $difficulty);
        }

        if ($coreTopicId = $request->query('core_clinical_topic_id')) {
            $query->whereHas('coreClinicalTopics', fn ($q) => $q->whereKey((int) $coreTopicId));
        }

        if ($medicalNodeId = $request->query('medical_taxonomy_node_id')) {
            $query->whereHas('medicalTaxonomyNodes', fn ($q) => $q->whereKey((int) $medicalNodeId));
        }

        if ($tagId = $request->query('tag_id')) {
            $query->whereHas('tags', fn ($q) => $q->whereKey((int) $tagId));
        }

        // Legacy bookmark: ?review=pending → status in_review
        if ($request->query('review') === 'pending' && ! $request->filled('status')) {
            $query->where('status', QuestionStatus::InReview->value);
        }

        if ($request->query('is_free') === '1') {
            $query->where('is_free', true);
        } elseif ($request->query('is_free') === '0') {
            $query->where('is_free', false);
        }

        if ($request->query('has_reports') === '1') {
            $query->where('stats_cache->total_reports', '>', 0);
        }

        $statsQuery = QuestionAccess::scopeVisibleTo(Question::query(), $actor);

        return view('admin::questions.index', [
            'questions' => $query->paginate(20)->withQueryString(),
            'statuses' => QuestionStatus::cases(),
            'difficulties' => Difficulty::cases(),
            'filters' => [
                'q' => $search,
                'status' => $request->query('status')
                    ?: ($request->query('review') === 'pending' ? QuestionStatus::InReview->value : null),
                'difficulty' => $request->query('difficulty'),
                'medical_taxonomy_node_id' => $request->query('medical_taxonomy_node_id'),
                'is_free' => $request->query('is_free'),
                'has_reports' => $request->query('has_reports'),
            ],
            'stats' => [
                'total' => (clone $statsQuery)->count(),
                'published' => (clone $statsQuery)->where('status', QuestionStatus::Published->value)->count(),
                'pending' => (clone $statsQuery)->where('status', QuestionStatus::InReview->value)->count(),
                'free' => (clone $statsQuery)->where('is_free', true)->count(),
            ],
            'canCreate' => $actor->can(Permission::QuestionCreate->value),
            'isReviewer' => QuestionAccess::isReviewer($actor),
        ]);
    }

    public function create(): View
    {
        $this->authorizePermission(Permission::QuestionCreate);

        return view('admin::questions.form', $this->formData(new Question([
            'status' => QuestionStatus::Draft,
            'difficulty' => Difficulty::Medium,
            'is_free' => false,
        ])));
    }

    public function store(Request $request, SaveAdminQuestionAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::QuestionCreate);

        $question = $action->handle($this->actor(), null, $this->validatedPayload($request));

        return redirect()
            ->route('admin.questions.edit', $question)
            ->with('status', QuestionAccess::isReviewer($this->actor())
                ? 'Đã tạo câu hỏi nháp.'
                : 'Đã tạo câu hỏi và gửi admin duyệt.');
    }

    public function edit(Question $question): View
    {
        $this->authorizePermission(Permission::QuestionView);
        QuestionAccess::authorizeView($this->actor(), $question);

        $question->load([
            'options' => fn ($q) => $q->orderBy('order'),
            'hints' => fn ($q) => $q->orderBy('sort_order'),
            'coreClinicalTopics.section',
            'medicalTaxonomyNodes',
            'tags',
            'creator:id,name,email',
            'reviewer:id,name',
            'pendingReviewRequest.requester:id,name',
        ]);

        return view('admin::questions.form', $this->formData($question));
    }

    public function stats(Question $question): View
    {
        $this->authorizePermission(Permission::QuestionView);
        QuestionAccess::authorizeView($this->actor(), $question);

        $question->load([
            'medicalTaxonomyNodes',
            'creator:id,name',
            'reviewer:id,name',
        ]);

        return view('admin::questions.stats', [
            'question' => $question,
            'stats' => $question->detailStats(),
            'isReviewer' => QuestionAccess::isReviewer($this->actor()),
        ]);
    }

    public function update(Request $request, Question $question, SaveAdminQuestionAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::QuestionUpdate);
        QuestionAccess::authorizeView($this->actor(), $question);

        $action->handle($this->actor(), $question, $this->validatedPayload($request));

        return back()->with('status', QuestionAccess::isReviewer($this->actor())
            ? 'Đã lưu câu hỏi.'
            : ($question->status === QuestionStatus::Published
                ? 'Đã gửi thay đổi để admin duyệt. Bản đang xuất bản chưa bị thay đổi.'
                : 'Đã lưu nội dung và cập nhật yêu cầu chờ duyệt.'));
    }

    public function destroy(Question $question, RequestQuestionDeletionAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::QuestionDelete);
        QuestionAccess::authorizeView($this->actor(), $question);

        $reviewer = QuestionAccess::isReviewer($this->actor());
        $action->handle($this->actor(), $question);

        return redirect()->route('admin.questions.index')->with(
            'status',
            $reviewer ? 'Đã xóa câu hỏi.' : 'Đã gửi yêu cầu xóa để admin duyệt.',
        );
    }

    public function transition(
        Request $request,
        Question $question,
        TransitionQuestionStatusAction $action,
    ): RedirectResponse {
        QuestionAccess::authorizeView($this->actor(), $question);
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(QuestionStatus::values())],
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $action->handle(
            $this->actor(),
            $question,
            QuestionStatus::from($data['status']),
            $data['rejection_reason'] ?? null,
        );

        return back()->with('status', 'Đã cập nhật trạng thái: '.QuestionStatus::from($data['status'])->label());
    }

    public function clone(Request $request, Question $question, CloneQuestionAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::QuestionCreate);
        QuestionAccess::authorizeView($this->actor(), $question);

        $fromVersion = $request->filled('from_version') ? (int) $request->input('from_version') : null;
        $clone = $action->handle($this->actor(), $question, $fromVersion);

        return redirect()
            ->route('admin.questions.edit', $clone)
            ->with('status', 'Đã nhân bản câu hỏi thành bản nháp mới.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Question $question): array
    {
        $pendingReview = $question->exists ? $question->pendingReviewRequest : null;
        $isReviewer = QuestionAccess::isReviewer($this->actor());
        $hasBlockingReview = $pendingReview !== null
            && $pendingReview->action !== QuestionReviewAction::Create;

        return [
            'question' => $question,
            'statuses' => QuestionStatus::cases(),
            'difficulties' => Difficulty::cases(),
            'canUpdate' => ($this->actor()->can(Permission::QuestionUpdate->value)
                || ($question->exists === false && $this->actor()->can(Permission::QuestionCreate->value)))
                && ($isReviewer || ! $hasBlockingReview),
            'canPublish' => $this->actor()->can(Permission::QuestionPublish->value),
            'canDelete' => $question->exists && $this->actor()->can(Permission::QuestionDelete->value),
            'canClone' => $question->exists && $this->actor()->can(Permission::QuestionCreate->value),
            'isReviewer' => $isReviewer,
            'pendingReview' => $pendingReview,
            'canViewAudit' => $this->actor()->can(Permission::AuditView->value),
        ];
    }

    /**
     * @return array{
     *     stem: string,
     *     stem_image_path: ?string,
     *     explanation: ?string,
     *     key_info: array<int, string>,
     *     attending_tip: ?string,
     *     difficulty: string,
     *     medical_taxonomy_node_ids: list<int>,
     *     is_free: bool,
     *     exam_flag: bool,
     *     options: list<array{id?: int|null, content: string, is_correct: bool, explanation?: ?string}>
     * }
     */
    private function validatedPayload(Request $request): array
    {
        $data = $request->validate([
            'stem' => ['required', 'string'],
            'stem_image_path' => ['nullable', 'string', 'max:1024'],
            'explanation' => ['nullable', 'string'],
            'key_info' => ['nullable', 'string'],
            'attending_tip' => ['nullable', 'string'],
            'difficulty' => ['required', Rule::in(Difficulty::values())],
            'core_clinical_topic_ids' => ['nullable', 'array'],
            'core_clinical_topic_ids.*' => ['integer', 'distinct', 'exists:core_clinical_topics,id'],
            'medical_taxonomy_node_ids' => ['required', 'array', 'min:1'],
            'medical_taxonomy_node_ids.*' => ['required', 'integer', 'distinct', 'exists:medical_taxonomy_nodes,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'distinct', 'exists:tags,id'],
            'hints' => ['nullable', 'array'],
            'hints.*.id' => ['nullable', 'integer'],
            'hints.*.content' => ['nullable', 'string', 'max:2000'],
            'is_free' => ['sometimes', 'boolean'],
            'exam_flag' => ['sometimes', 'boolean'],
            'options' => ['required', 'array', 'min:2'],
            'options.*.id' => ['nullable', 'integer'],
            'options.*.content' => ['required', 'string'],
            'options.*.is_correct' => ['sometimes', 'boolean'],
            'options.*.explanation' => ['nullable', 'string'],
        ], [
            'stem.required' => 'Vui lòng nhập nội dung câu hỏi.',
            'medical_taxonomy_node_ids.required' => 'Vui lòng chọn ít nhất một mục danh mục y khoa.',
            'medical_taxonomy_node_ids.min' => 'Vui lòng chọn ít nhất một mục danh mục y khoa.',
            'options.required' => 'Vui lòng thêm đáp án.',
            'options.min' => 'Cần ít nhất 2 đáp án.',
            'options.*.content.required' => 'Nội dung đáp án không được để trống.',
        ]);

        $options = [];

        foreach ($data['options'] as $row) {
            $rawCorrect = $row['is_correct'] ?? false;
            $options[] = [
                'id' => isset($row['id']) ? (int) $row['id'] : null,
                'content' => $row['content'],
                'is_correct' => $rawCorrect === true || $rawCorrect === 1 || $rawCorrect === '1',
                'explanation' => $row['explanation'] ?? null,
            ];
        }

        $hints = [];
        foreach ($data['hints'] ?? [] as $row) {
            $hints[] = [
                'id' => isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null,
                'content' => (string) ($row['content'] ?? ''),
            ];
        }

        $payload = [
            'stem' => $data['stem'],
            'stem_image_path' => $data['stem_image_path'] ?? null,
            'explanation' => $data['explanation'] ?? null,
            'key_info' => $this->parseKeyInfo($data['key_info'] ?? null),
            'attending_tip' => $data['attending_tip'] ?? null,
            'difficulty' => $data['difficulty'],
            'core_clinical_topic_ids' => collect($data['core_clinical_topic_ids'] ?? [])
                ->map(fn ($id): int => (int) $id)->unique()->values()->all(),
            'medical_taxonomy_node_ids' => collect($data['medical_taxonomy_node_ids'] ?? [])
                ->map(fn ($id): int => (int) $id)->unique()->values()->all(),
            'tag_ids' => collect($data['tag_ids'] ?? [])
                ->map(fn ($id): int => (int) $id)->unique()->values()->all(),
            'is_free' => $request->boolean('is_free'),
            'exam_flag' => $request->boolean('exam_flag'),
            'options' => $options,
        ];

        if ($request->exists('hints')) {
            $payload['hints'] = $hints;
        }

        return $payload;
    }

    /**
     * @return array<int, string>
     */
    private function parseKeyInfo(?string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/u', (string) $raw) ?: [];

        return collect($lines)
            ->map(fn (string $line): string => trim(strip_tags($line)))
            ->filter(fn (string $line): bool => $line !== '')
            ->unique()
            ->values()
            ->all();
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
