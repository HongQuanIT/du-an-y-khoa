<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Admin\Actions\CloneQuestionAction;
use Modules\Admin\Actions\RequestQuestionDeletionAction;
use Modules\Admin\Actions\SaveAdminQuestionAction;
use Modules\Admin\Actions\TransitionQuestionStatusAction;
use Modules\Admin\Support\AdminQuestionListQuery;
use Modules\Admin\Support\QuestionAccess;
use Modules\QuestionBank\Actions\SyncQuestionStatsAction;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionReviewAction;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionFeedback;
use Modules\QuestionBank\Models\QuestionImportBatch;

final class QuestionController extends Controller
{
    public function index(Request $request): View
    {
        QuestionAccess::authorizeWorkspace($this->actor());

        $actor = $this->actor();
        $listQuery = app(AdminQuestionListQuery::class);
        $query = $listQuery->apply(
            Question::query()
                ->with(['lessons', 'creator:id,name', 'instructor:id,name', 'instructorSlot1:id,name', 'instructorSlot2:id,name', 'publisher:id,name', 'pendingReviewRequest.requester:id,name', 'reviewRequests.reviewer:id,name', 'clonedFrom:id,code,stem'])
                ->withCount([
                    'feedback',
                    'feedback as pending_feedback_count' => fn ($q) => $q->where('status', QuestionFeedback::STATUS_PENDING),
                ]),
            $request,
            $actor,
        )->latest('updated_at');

        $search = trim((string) $request->query('q', ''));
        $statusFilters = AdminQuestionListQuery::stringValues($request->query('status'));
        if ($statusFilters === [] && $request->query('review') === 'pending') {
            $statusFilters = [QuestionStatus::InReview->value];
        }
        $difficultyFilters = AdminQuestionListQuery::stringValues($request->query('difficulty'));
        $accessFilters = AdminQuestionListQuery::stringValues($request->query('is_free'));
        $creatorIds = AdminQuestionListQuery::integerIds($request->query('created_by'));
        $statsQuery = QuestionAccess::scopeVisibleTo(Question::query(), $actor);

        $questions = $query->paginate(20)->withQueryString();
        $this->ensureListStatsAreFresh($questions->getCollection());

        $importBatchId = $request->query('import_batch_id');
        $importBatch = filled($importBatchId)
            ? QuestionImportBatch::query()->find((string) $importBatchId)
            : null;

        return view('admin::questions.index', [
            'questions' => $questions,
            'statuses' => QuestionStatus::cases(),
            'difficulties' => Difficulty::cases(),
            'importBatch' => $importBatch,
            'filters' => [
                'q' => $search,
                'status' => $statusFilters,
                'difficulty' => $difficultyFilters,
                'lesson_id' => $request->query('lesson_id'),
                'is_free' => $accessFilters,
                'created_by' => $creatorIds,
                'import_batch_id' => $importBatchId,
            ],
            'stats' => [
                'total' => (clone $statsQuery)->count(),
                'published' => (clone $statsQuery)->where('status', QuestionStatus::Published->value)->count(),
                'pending' => (clone $statsQuery)->where('status', QuestionStatus::InReview->value)->count(),
                'free' => (clone $statsQuery)->where('is_free', true)->count(),
            ],
            'canCreate' => $actor->can(Permission::QuestionCreate->value),
            'isReviewer' => QuestionAccess::isReviewer($actor),
            'creatorOptions' => $this->creatorFilterOptions($actor),
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

    public function store(
        Request $request,
        SaveAdminQuestionAction $action,
        TransitionQuestionStatusAction $transition,
    ): RedirectResponse {
        $this->authorizePermission(Permission::QuestionCreate);

        $question = $action->handle($this->actor(), null, $this->validatedPayload($request));
        $requestedStatus = $request->filled('requested_status')
            ? QuestionStatus::from($request->validate([
                'requested_status' => ['required', 'string', Rule::in(QuestionStatus::values())],
            ])['requested_status'])
            : QuestionStatus::Draft;

        if ($requestedStatus !== QuestionStatus::Draft) {
            $transition->handle($this->actor(), $question, $requestedStatus);
        }

        return redirect()
            ->route('admin.questions.edit', $question)
            ->with('status', QuestionAccess::canPublish($this->actor()) && ! QuestionAccess::canEdit($this->actor())
                ? 'Đã tạo câu hỏi với trạng thái: '.$requestedStatus->label().'.'
                : ($requestedStatus === QuestionStatus::InReview
                    ? 'Đã tạo câu hỏi và gửi giảng viên duyệt.'
                    : 'Đã tạo bản nháp câu hỏi.'));
    }

    public function edit(Question $question): View
    {
        QuestionAccess::authorizeWorkspace($this->actor());
        QuestionAccess::authorizeView($this->actor(), $question);

        $question->load([
            'options' => fn ($q) => $q->orderBy('order'),
            'hints' => fn ($q) => $q->orderBy('sort_order'),
            'lessons.subjects.organSystems',
            'tags',
            'creator:id,name,email',
            'instructor:id,name',
            'instructorSlot1:id,name',
            'instructorSlot2:id,name',
            'publisher:id,name',
            'reviewer:id,name',
            'pendingReviewRequest.requester:id,name',
            'latestRejectedReviewRequest.reviewer:id,name',
        ]);

        return view('admin::questions.form', $this->formData($question));
    }

    public function stats(Question $question): View
    {
        QuestionAccess::authorizeWorkspace($this->actor());
        QuestionAccess::authorizeView($this->actor(), $question);

        $question->load([
            'lessons',
            'creator:id,name',
            'reviewer:id,name',
        ]);

        app(SyncQuestionStatsAction::class)->syncForQuestion($question);
        $question->refresh();

        return view('admin::questions.stats', [
            'question' => $question,
            'stats' => $question->detailStats(),
            'isReviewer' => QuestionAccess::isReviewer($this->actor()),
        ]);
    }

    public function update(
        Request $request,
        Question $question,
        SaveAdminQuestionAction $action,
        TransitionQuestionStatusAction $transition,
    ): RedirectResponse {
        $this->authorizePermission(Permission::QuestionUpdate);
        QuestionAccess::authorizeView($this->actor(), $question);

        // Chờ xuất bản: không lưu nội dung qua form soạn — chỉ chuyển trạng thái (nếu có).
        if ($question->status === QuestionStatus::PendingPublish) {
            if ($request->filled('requested_status')) {
                $statusData = $request->validate([
                    'requested_status' => ['required', 'string', Rule::in(QuestionStatus::values())],
                    'rejection_reason' => ['nullable', 'string', 'max:2000'],
                ]);

                $transition->handle(
                    $this->actor(),
                    $question,
                    QuestionStatus::from($statusData['requested_status']),
                    $statusData['rejection_reason'] ?? null,
                );

                return back()->with(
                    'status',
                    'Đã cập nhật trạng thái: '.QuestionStatus::from($statusData['requested_status'])->label(),
                );
            }

            return back()->withErrors([
                'status' => 'Câu đang chờ xuất bản. Dùng nút «Duyệt & xuất bản» hoặc «Từ chối xuất bản» bên phải.',
            ]);
        }

        $question = $action->handle($this->actor(), $question, $this->validatedPayload($request));

        if ($request->filled('requested_status')) {
            $statusData = $request->validate([
                'requested_status' => ['required', 'string', Rule::in(QuestionStatus::values())],
                'rejection_reason' => ['nullable', 'string', 'max:2000'],
            ]);

            $wasInReview = $question->status === QuestionStatus::InReview;
            $nextStatus = QuestionStatus::from($statusData['requested_status']);

            $transition->handle(
                $this->actor(),
                $question,
                $nextStatus,
                $statusData['rejection_reason'] ?? null,
            );

            $resubmitted = $wasInReview && $nextStatus === QuestionStatus::InReview;

            return back()->with('status', $resubmitted
                ? 'Đã lưu và gửi duyệt lại. Hai phiếu giảng viên được reset.'
                : ($nextStatus === QuestionStatus::InReview
                    ? 'Đã lưu câu hỏi và gửi giảng viên duyệt.'
                    : 'Đã lưu câu hỏi và cập nhật trạng thái: '.$nextStatus->label()));
        }

        return back()->with('status', match (true) {
            $question->status === QuestionStatus::Draft && $question->published_version => 'Đã lưu bản làm việc. Ngân hàng vẫn phục vụ phiên bản đã xuất bản cho đến khi giảng viên duyệt và admin xuất bản lại.',
            default => 'Đã lưu bản nháp câu hỏi.',
        });
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
            ->with('status', QuestionAccess::isReviewer($this->actor())
                ? 'Đã nhân bản câu hỏi thành bản nháp mới.'
                : 'Đã nhân bản câu hỏi. Bản nháp mới đang chờ admin duyệt trước khi xuất bản.');
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
            'workflowStatuses' => $question->exists
                ? $this->workflowStatuses($question->status, $isReviewer)
                : [],
            'difficulties' => Difficulty::cases(),
            'canUpdate' => (
                ($question->exists === false && $this->actor()->can(Permission::QuestionCreate->value))
                || ($this->actor()->can(Permission::QuestionUpdate->value) && ! $hasBlockingReview)
            ),
            // Nội dung khóa khi chờ xuất bản / đã retire — chỉ dùng panel xuất bản hoặc chuyển trạng thái riêng.
            'canEditContent' => (
                ($question->exists === false && $this->actor()->can(Permission::QuestionCreate->value))
                || (
                    $this->actor()->can(Permission::QuestionUpdate->value)
                    && ! $hasBlockingReview
                    && ! in_array($question->status, [
                        QuestionStatus::PendingPublish,
                        QuestionStatus::Retired,
                    ], true)
                )
            ),
            'canPublish' => $this->actor()->can(Permission::QuestionPublish->value),
            'canSubmit' => $this->actor()->can(Permission::QuestionSubmit->value),
            'canRetire' => $this->actor()->can(Permission::QuestionRetire->value),
            'canDelete' => $question->exists && $this->actor()->can(Permission::QuestionDelete->value),
            'canClone' => $question->exists && $this->actor()->can(Permission::QuestionCreate->value),
            'isReviewer' => $isReviewer,
            'pendingReview' => $pendingReview,
            'latestRejectedReview' => $question->exists ? $question->latestRejectedReviewRequest : null,
            'canViewAudit' => $this->actor()->can(Permission::AuditView->value),
        ];
    }

    /**
     * @return list<QuestionStatus>
     */
    private function workflowStatuses(QuestionStatus $current, bool $isReviewer): array
    {
        $canUpdate = $this->actor()->can(Permission::QuestionUpdate->value);
        $canSubmit = $this->actor()->can(Permission::QuestionSubmit->value);
        $canPublish = $this->actor()->can(Permission::QuestionPublish->value);
        $canRetire = $this->actor()->can(Permission::QuestionRetire->value);
        unset($isReviewer);

        return match ($current) {
            QuestionStatus::Draft => $canSubmit
                ? [QuestionStatus::InReview]
                : [],
            QuestionStatus::InReview => $canSubmit
                ? [QuestionStatus::Draft]
                : [],
            QuestionStatus::PendingPublish => $canPublish
                ? [QuestionStatus::Published, QuestionStatus::Private, QuestionStatus::Rejected]
                : [],
            QuestionStatus::Published => array_values(array_filter([
                $canPublish ? QuestionStatus::Private : null,
                $canRetire ? QuestionStatus::Retired : null,
            ])),
            QuestionStatus::Private => array_values(array_filter([
                $canPublish ? QuestionStatus::Published : null,
                $canRetire ? QuestionStatus::Retired : null,
            ])),
            QuestionStatus::Rejected, QuestionStatus::Retired => $canUpdate
                ? [QuestionStatus::Draft]
                : [],
        };
    }

    /**
     * @return array{
     *     stem: string,
     *     stem_image_path: ?string,
     *     key_info: array<int, string>,
     *     attending_tip: ?string,
     *     difficulty: string,
     *     lesson_ids: list<int>,
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
            'key_info' => ['nullable', 'string'],
            'attending_tip' => ['nullable', 'string'],
            'difficulty' => ['required', Rule::in(Difficulty::values())],
            'lesson_ids' => ['required', 'array', 'min:1'],
            'lesson_ids.*' => ['required', 'integer', 'distinct', 'exists:lessons,id'],
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
            'lesson_ids.required' => 'Vui lòng chọn ít nhất một bài học.',
            'lesson_ids.min' => 'Vui lòng chọn ít nhất một bài học.',
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
            'key_info' => $this->parseKeyInfo($data['key_info'] ?? null),
            'attending_tip' => $data['attending_tip'] ?? null,
            'difficulty' => $data['difficulty'],
            'lesson_ids' => collect($data['lesson_ids'] ?? [])
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

    /**
     * @param  Collection<int, Question>  $questions
     */
    private function ensureListStatsAreFresh(Collection $questions): void
    {
        if ($questions->isEmpty()) {
            return;
        }

        $idsOnPage = $questions
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->values()
            ->all();

        $idsWithAttempts = DB::table('question_attempts')
            ->whereIn('question_id', $idsOnPage)
            ->whereNotNull('is_correct')
            ->distinct()
            ->pluck('question_id')
            ->map(fn ($id): string => (string) $id);

        $questionIds = $questions
            ->filter(function (Question $question) use ($idsWithAttempts): bool {
                if ($question->stats_cache === null || $question->stats_updated_at === null) {
                    return true;
                }

                $cachedAttempts = (int) ($question->stats_cache['total_attempts'] ?? 0);

                return $cachedAttempts === 0 && $idsWithAttempts->contains((string) $question->getKey());
            })
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->values()
            ->all();

        if ($questionIds === []) {
            return;
        }

        app(SyncQuestionStatsAction::class)->syncForQuestionIds($questionIds);

        $synced = array_flip($questionIds);
        $questions->each(function (Question $question) use ($synced): void {
            if (isset($synced[(string) $question->getKey()])) {
                $question->refresh();
            }
        });
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    private function creatorFilterOptions(User $actor): array
    {
        if (! QuestionAccess::isReviewer($actor)) {
            return [];
        }

        return User::query()
            ->select('users.id', 'users.name')
            ->whereHas('createdQuestions')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => (string) $user->getKey(),
                'label' => (string) $user->name,
            ])
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
