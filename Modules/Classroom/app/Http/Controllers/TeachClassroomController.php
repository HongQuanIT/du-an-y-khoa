<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Audit\AuditContext;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Audit\Enums\AuditPortal;
use App\Support\Enums\Entitlement;
use App\Support\Enums\Role;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Classroom\Actions\CloseClassroomAction;
use Modules\Classroom\Actions\CreateClassroomAction;
use Modules\Classroom\Actions\EndLiveSessionAction;
use Modules\Classroom\Actions\ReopenClassroomAction;
use Modules\Classroom\Actions\ScheduleLiveSessionAction;
use Modules\Classroom\Actions\StartLiveSessionAction;
use Modules\Classroom\Actions\UpdateTeachClassroomAction;
use Modules\Classroom\Enums\ClassroomPurpose;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\ClassroomVisibility;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Enums\MemberRole;
use Modules\Classroom\Enums\MemberStatus;
use Modules\Classroom\Http\Requests\ScheduleSessionRequest;
use Modules\Classroom\Http\Requests\StoreTeachClassroomRequest;
use Modules\Classroom\Http\Requests\UpdateTeachClassroomRequest;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\LiveSession;
use Modules\Classroom\Services\LiveKitTokenService;
use Modules\Exam\Enums\ExamStatus;
use Modules\Exam\Models\Exam;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\CoreClinicalTopic;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionFeedback;
use Modules\QuestionBank\Models\Tag;
use Modules\QuestionBank\Support\MedicalTaxonomyNodeTypes;

final class TeachClassroomController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        assert($user !== null);

        $classrooms = Classroom::query()
            ->whereIn('purpose', array_map(
                static fn (ClassroomPurpose $p): string => $p->value,
                ClassroomPurpose::teachCases(),
            ))
            ->whereHas('members', function ($query) use ($user): void {
                $query->where('user_id', $user->getKey())
                    ->where('status', MemberStatus::Active->value)
                    ->whereIn('role_in_class', [MemberRole::Host->value, MemberRole::Cohost->value]);
            })
            ->with(['host', 'liveSession', 'upcomingSession'])
            ->withCount('activeMembers')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $baseQuery = Classroom::query()
            ->whereIn('purpose', array_map(
                static fn (ClassroomPurpose $p): string => $p->value,
                ClassroomPurpose::teachCases(),
            ))
            ->whereHas('members', function ($query) use ($user): void {
                $query->where('user_id', $user->getKey())
                    ->where('status', MemberStatus::Active->value)
                    ->whereIn('role_in_class', [MemberRole::Host->value, MemberRole::Cohost->value]);
            });

        return view('classroom::teach.classes.index', [
            'classrooms' => $classrooms,
            'stats' => [
                'total' => (clone $baseQuery)->count(),
                'live' => (clone $baseQuery)->whereHas('liveSession')->count(),
                'upcoming' => (clone $baseQuery)->whereHas('upcomingSession')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Classroom::class);

        return view('classroom::teach.classes.create', [
            'purposes' => ClassroomPurpose::teachCases(),
            'visibilities' => ClassroomVisibility::cases(),
        ]);
    }

    public function store(StoreTeachClassroomRequest $request, CreateClassroomAction $action): RedirectResponse
    {
        $this->authorize('create', Classroom::class);

        $classroom = $action->handle($request->user(), $request->validated());

        return redirect()
            ->route('teach.classes.show', $classroom)
            ->with('status', 'Đã gửi lớp chờ duyệt. Admin sẽ duyệt trước khi hiển thị cho học viên.');
    }

    public function edit(Request $request, Classroom $classroom): View
    {
        $this->authorizeTeachClassroom($request, $classroom);
        $this->authorize('update', $classroom);

        return view('classroom::teach.classes.edit', [
            'classroom' => $classroom,
            'purposes' => ClassroomPurpose::teachCases(),
            'visibilities' => ClassroomVisibility::cases(),
        ]);
    }

    public function update(
        UpdateTeachClassroomRequest $request,
        Classroom $classroom,
        UpdateTeachClassroomAction $action,
    ): RedirectResponse {
        $this->authorizeTeachClassroom($request, $classroom);
        $this->authorize('update', $classroom);

        $previousStatus = $classroom->status;
        $classroom = $action->handle($request->user(), $classroom, $request->validated());
        $requiresApproval = $previousStatus !== ClassroomStatus::PendingApproval
            && $classroom->status === ClassroomStatus::PendingApproval;

        return redirect()
            ->route('teach.classes.show', $classroom)
            ->with('status', $requiresApproval
                ? 'Đã cập nhật nội dung quan trọng. Lớp được chuyển về chờ admin duyệt lại.'
                : 'Đã cập nhật thông tin lớp.');
    }

    public function show(Request $request, Classroom $classroom): View
    {
        $this->authorizeTeachClassroom($request, $classroom);

        $classroom->load(['host', 'liveSession'])->loadCount('activeMembers');

        $upcomingSessions = $classroom->sessions()
            ->where('status', LiveSessionStatus::Scheduled->value)
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get();

        $pastSessions = $classroom->sessions()
            ->whereIn('status', [LiveSessionStatus::Ended->value, LiveSessionStatus::Cancelled->value])
            ->limit(5)
            ->get();

        $members = $classroom->activeMembers()
            ->with('user')
            ->latest('joined_at')
            ->limit(50)
            ->get();

        return view('classroom::teach.classes.show', [
            'classroom' => $classroom,
            'publishedExams' => $classroom->purpose === ClassroomPurpose::ExamReview
                ? Exam::query()
                    ->where('status', ExamStatus::Published->value)
                    ->withCount('questions')
                    ->orderBy('title')
                    ->get(['id', 'title', 'description', 'duration_minutes'])
                : collect(),
            'upcomingSessions' => $upcomingSessions,
            'pastSessions' => $pastSessions,
            'members' => $members,
            'canCloseClassroom' => $classroom->status === ClassroomStatus::Active
                && $classroom->liveSession === null
                && $classroom->sessions()->where('status', LiveSessionStatus::Ended->value)->exists(),
        ]);
    }

    public function destroy(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeTeachClassroom($request, $classroom);
        $this->authorize('manageLive', $classroom);
        abort_if($classroom->liveSession()->exists(), 409, 'Hãy kết thúc buổi live trước khi xoá lớp.');

        Auditor::record(
            AuditAction::ClassroomDeleted,
            $request->user(),
            $classroom,
            before: ['title' => $classroom->title, 'status' => $classroom->status->value],
            context: new AuditContext(portal: AuditPortal::Teach),
        );
        $classroom->delete();

        return redirect()
            ->route('teach.classes.index')
            ->with('status', 'Đã xoá lớp: '.$classroom->title);
    }

    public function close(Request $request, Classroom $classroom, CloseClassroomAction $action): RedirectResponse
    {
        $this->authorizeTeachClassroom($request, $classroom);
        $this->authorize('manageLive', $classroom);
        $action->handle($request->user(), $classroom);

        return redirect()
            ->route('teach.classes.show', $classroom)
            ->with('status', 'Đã đóng lớp. Học viên không thể tham gia hoặc vào buổi live mới.');
    }

    public function reopen(Request $request, Classroom $classroom, ReopenClassroomAction $action): RedirectResponse
    {
        $this->authorizeTeachClassroom($request, $classroom);
        $this->authorize('manageLive', $classroom);
        $action->handle($request->user(), $classroom);

        return redirect()
            ->route('teach.classes.show', $classroom)
            ->with('status', 'Đã mở lại lớp. Phê duyệt trước đó được giữ nguyên và học viên có thể truy cập lại.');
    }

    public function scheduleLive(ScheduleSessionRequest $request, Classroom $classroom, ScheduleLiveSessionAction $action): RedirectResponse
    {
        $this->authorizeTeachClassroom($request, $classroom);
        $this->authorize('manageLive', $classroom);
        abort_if($classroom->status === ClassroomStatus::Closed, 409, 'Lớp đã đóng. Không thể lên lịch buổi live mới.');
        $session = $action->handle($classroom, $request->sessionPayload());
        $this->auditLive($request, AuditAction::ClassroomLiveScheduled, $classroom, $session);

        return redirect()->route('teach.classes.show', $classroom)->with('status', 'Đã lên lịch buổi live: '.$session->title);
    }

    public function searchQuestions(Request $request, Classroom $classroom): JsonResponse
    {
        $this->authorizeTeachClassroom($request, $classroom);
        $this->authorize('manageLive', $classroom);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'core_clinical_topic_ids' => ['nullable', 'array', 'max:10'],
            'core_clinical_topic_ids.*' => ['integer', 'exists:core_clinical_topics,id'],
            'medical_taxonomy_node_ids' => ['nullable', 'array', 'max:10'],
            'medical_taxonomy_node_ids.*' => ['integer', 'exists:medical_taxonomy_nodes,id'],
            'tag_ids' => ['nullable', 'array', 'max:10'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'feedback_categories' => ['nullable', 'array', 'max:8'],
            'feedback_categories.*' => ['string', 'in:grammar,incorrect,missing,improvement,technical,media,search,other'],
        ]);
        $search = trim((string) ($validated['q'] ?? ''));
        $coreTopicIds = collect($validated['core_clinical_topic_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $topicIds = collect($validated['medical_taxonomy_node_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $tagIds = collect($validated['tag_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $feedbackCategories = collect($validated['feedback_categories'] ?? [])
            ->map(fn ($category): string => (string) $category)
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Chỉ trả về câu đã xuất bản và đúng quyền QBank của giảng viên.
        $questions = Question::query()
            ->with([
                'coreClinicalTopics:id,name,blueprint_section_id',
                'coreClinicalTopics.section:id,name',
                'latestFeedback.user:id,name',
                'medicalTaxonomyNodes:id,name,node_type',
                'tags:id,name',
            ])
            ->withCount([
                'feedback',
                'feedback as pending_feedback_count' => fn ($feedback) => $feedback->where('status', QuestionFeedback::STATUS_PENDING),
            ])
            ->where('status', QuestionStatus::Published)
            ->when(
                ! $request->user()->hasEntitlement(Entitlement::QbankFull->value),
                fn ($query) => $query->where('is_free', true),
            )
            ->when($search !== '', function ($query) use ($search): void {
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
                $query->where(function ($questions) use ($pattern): void {
                    $questions
                        ->whereRaw("stem LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereHas(
                            'coreClinicalTopics',
                            fn ($topics) => $topics->whereRaw("name LIKE ? ESCAPE '!'", [$pattern]),
                        )
                        ->orWhereHas(
                            'medicalTaxonomyNodes',
                            fn ($nodes) => $nodes->whereRaw("name LIKE ? ESCAPE '!'", [$pattern]),
                        )
                        ->orWhereHas(
                            'tags',
                            fn ($tags) => $tags->whereRaw("name LIKE ? ESCAPE '!'", [$pattern]),
                        );
                });
            })
            ->when($coreTopicIds !== [], function ($query) use ($coreTopicIds): void {
                $query->whereHas(
                    'coreClinicalTopics',
                    fn ($topics) => $topics->whereIn('core_clinical_topics.id', $coreTopicIds),
                );
            })
            ->when($topicIds !== [], function ($query) use ($topicIds): void {
                $query->whereHas(
                    'medicalTaxonomyNodes',
                    fn ($nodes) => $nodes->whereIn('medical_taxonomy_nodes.id', $topicIds),
                );
            })
            ->when($tagIds !== [], function ($query) use ($tagIds): void {
                $query->whereHas(
                    'tags',
                    fn ($tags) => $tags->whereIn('tags.id', $tagIds),
                );
            })
            ->when($feedbackCategories !== [], function ($query) use ($feedbackCategories): void {
                $query->whereHas(
                    'feedback',
                    fn ($feedback) => $feedback->whereIn('category', $feedbackCategories),
                );
            })
            ->latest()
            ->limit(30)
            ->get(['id', 'stem', 'difficulty']);

        $availableQuestionScope = function ($query) use ($request): void {
            $query->where('status', QuestionStatus::Published)
                ->when(
                    ! $request->user()->hasEntitlement(Entitlement::QbankFull->value),
                    fn ($questions) => $questions->where('is_free', true),
                );
        };

        $coreTopicOptions = CoreClinicalTopic::query()
            ->with(['section:id,name'])
            ->whereHas('questions', $availableQuestionScope)
            ->orderBy('name')
            ->limit(80)
            ->get(['id', 'blueprint_section_id', 'name']);

        $topicOptions = MedicalTaxonomyNode::query()
            ->whereHas('questions', $availableQuestionScope)
            ->orderBy('name')
            ->limit(80)
            ->get(['id', 'name', 'node_type']);

        $tagOptions = Tag::query()
            ->whereHas('questions', $availableQuestionScope)
            ->orderBy('name')
            ->limit(80)
            ->get(['id', 'name']);

        return ApiResponse::item([
            'filters' => [
                'core_topics' => $coreTopicOptions->map(fn (CoreClinicalTopic $topic): array => [
                    'id' => (int) $topic->getKey(),
                    'name' => $topic->name,
                    'section_name' => $topic->section?->name,
                ])->values(),
                'medical_groups' => collect(MedicalTaxonomyNodeTypes::GROUPS)
                    ->map(fn (array $group, string $key): array => [
                        'key' => $key,
                        'label' => $group['label'],
                        'icon' => $group['icon'],
                        'types' => $group['types'],
                    ])
                    ->values(),
                'medical_nodes' => $topicOptions->map(fn (MedicalTaxonomyNode $node): array => [
                    'id' => (int) $node->getKey(),
                    'name' => $node->name,
                    'node_type' => $node->node_type,
                    'node_type_label' => MedicalTaxonomyNodeTypes::label($node->node_type),
                    'group_key' => MedicalTaxonomyNodeTypes::groupKey($node->node_type),
                ])->values(),
                'tags' => $tagOptions->map(fn (Tag $tag): array => [
                    'id' => (int) $tag->getKey(),
                    'name' => $tag->name,
                ])->values(),
                'feedback_categories' => [
                    ['value' => 'grammar', 'label' => 'Ngữ pháp và chính tả'],
                    ['value' => 'incorrect', 'label' => 'Nội dung không chính xác'],
                    ['value' => 'missing', 'label' => 'Nội dung bị thiếu'],
                    ['value' => 'improvement', 'label' => 'Cần cải thiện nội dung'],
                    ['value' => 'technical', 'label' => 'Sự cố kỹ thuật'],
                    ['value' => 'media', 'label' => 'Phản hồi hình ảnh'],
                    ['value' => 'search', 'label' => 'Kết quả tìm kiếm'],
                    ['value' => 'other', 'label' => 'Khác'],
                ],
            ],
            'questions' => $questions->map(fn (Question $question): array => [
                'id' => (string) $question->getKey(),
                'stem' => trim(strip_tags(html_entity_decode($question->stem, ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
                'difficulty' => $question->difficulty->label(),
                'topic' => $question->medicalTaxonomyNodes->pluck('name')->join(', ') ?: 'Tổng hợp',
                'core_topics' => $question->coreClinicalTopics->map(fn (CoreClinicalTopic $topic): array => [
                    'id' => (int) $topic->getKey(),
                    'name' => $topic->name,
                    'section_name' => $topic->section?->name,
                ])->values(),
                'topics' => $question->medicalTaxonomyNodes->pluck('name')->values(),
                'medical_taxonomy_node_ids' => $question->medicalTaxonomyNodes->pluck('id')->map(fn ($id): int => (int) $id)->values(),
                'topic_ids' => $question->medicalTaxonomyNodes->pluck('id')->map(fn ($id): int => (int) $id)->values(),
                'tags' => $question->tags->map(fn (Tag $tag): array => [
                    'id' => (int) $tag->getKey(),
                    'name' => $tag->name,
                ])->values(),
                'feedback_count' => (int) ($question->feedback_count ?? 0),
                'pending_feedback_count' => (int) ($question->pending_feedback_count ?? 0),
                'latest_feedback' => $question->latestFeedback ? [
                    'target' => QuestionFeedback::targetLabels()[$question->latestFeedback->target] ?? $question->latestFeedback->target,
                    'category' => QuestionFeedback::categoryLabels()[$question->latestFeedback->category] ?? $question->latestFeedback->category,
                    'status' => QuestionFeedback::statusLabels()[$question->latestFeedback->status] ?? $question->latestFeedback->status,
                    'message' => $question->latestFeedback->message,
                    'user' => $question->latestFeedback->user?->name,
                    'created_at' => $question->latestFeedback->created_at?->format('d/m/Y H:i'),
                ] : null,
            ])->values(),
        ]);
    }

    public function questionFeedback(Request $request, Classroom $classroom, Question $question): JsonResponse
    {
        $this->authorizeTeachClassroom($request, $classroom);
        $this->authorize('manageLive', $classroom);
        abort_unless($classroom->purpose === ClassroomPurpose::FeedbackReview, 404);
        abort_unless($question->status === QuestionStatus::Published, 404);
        abort_if(
            ! $question->is_free && ! $request->user()->hasEntitlement(Entitlement::QbankFull->value),
            404,
        );

        $feedback = $question->feedback()
            ->with(['user:id,name', 'option:id,question_id,label,content'])
            ->latest()
            ->limit(100)
            ->get();

        return ApiResponse::item([
            'question' => [
                'id' => (string) $question->getKey(),
                'stem' => trim(strip_tags(html_entity_decode($question->stem, ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
            ],
            'total' => $question->feedback()->count(),
            'feedback' => $feedback->map(fn (QuestionFeedback $item): array => [
                'id' => (int) $item->getKey(),
                'target' => QuestionFeedback::targetLabels()[$item->target] ?? $item->target,
                'category' => QuestionFeedback::categoryLabels()[$item->category] ?? $item->category,
                'status' => QuestionFeedback::statusLabels()[$item->status] ?? $item->status,
                'message' => $item->message ?: 'Không có ghi chú thêm.',
                'student' => $item->user?->name ?? 'Học viên không xác định',
                'created_at' => $item->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                'option' => $item->option ? [
                    'label' => $item->option->label,
                    'content' => trim(strip_tags(html_entity_decode($item->option->content, ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
                ] : null,
            ])->values(),
        ]);
    }

    public function startLive(Request $request, Classroom $classroom, LiveSession $liveSession, StartLiveSessionAction $action): RedirectResponse
    {
        $this->authorizeTeachClassroom($request, $classroom);
        // Instructors may test/host a pending classroom; learners remain blocked until approval.
        $this->authorize('manageLive', $classroom);
        abort_if($classroom->status === ClassroomStatus::Closed, 409, 'Lớp đã đóng. Không thể bắt đầu buổi live.');
        $action->handle($classroom, $liveSession, allowReopen: true);
        $this->auditLive($request, AuditAction::ClassroomLiveStarted, $classroom, $liveSession->fresh() ?? $liveSession);

        return redirect()->route('teach.classes.sessions.studio', [$classroom, $liveSession]);
    }

    public function studio(Request $request, Classroom $classroom, LiveSession $liveSession, LiveKitTokenService $tokens): View|RedirectResponse
    {
        $this->authorizeTeachClassroom($request, $classroom);
        $this->authorize('manageLive', $classroom);
        if ($liveSession->status === LiveSessionStatus::Ended) {
            return redirect()
                ->route('teach.classes.show', $classroom)
                ->with('status', 'Buổi live đã kết thúc.');
        }
        abort_if($liveSession->status === LiveSessionStatus::Cancelled, 409, 'Buổi live đã bị hủy.');

        $role = $classroom->roleFor($request->user()) ?? MemberRole::Host;
        $liveSession->load([
            'messages' => fn ($query) => $query
                ->where('is_hidden', false)
                ->with('user')
                ->latest('created_at')
                ->limit(100),
        ]);

        return view('classroom::teach.classes.studio', [
            'classroom' => $classroom,
            'session' => $liveSession,
            'tokenPayload' => $tokens->issue($liveSession, $request->user(), $role),
            'livekitConfigured' => $tokens->isConfigured(),
            'messages' => $liveSession->messages->sortBy('created_at')->values(),
            'role' => $role,
            'canModerate' => true,
            'canHostLive' => true,
            'chatReadonly' => false,
        ]);
    }

    public function endLive(Request $request, Classroom $classroom, LiveSession $liveSession, EndLiveSessionAction $action): RedirectResponse
    {
        $this->authorizeTeachClassroom($request, $classroom);
        $this->authorize('manageLive', $classroom);
        $action->handle($classroom, $liveSession);
        $this->auditLive($request, AuditAction::ClassroomLiveEnded, $classroom, $liveSession->fresh() ?? $liveSession);

        return redirect()->route('teach.classes.show', $classroom)->with('status', 'Buổi live đã kết thúc.');
    }

    private function authorizeTeachClassroom(Request $request, Classroom $classroom): void
    {
        $user = $request->user();
        assert($user !== null);

        abort_unless($classroom->purpose->isTeachPurpose(), 404);
        abort_unless(
            $classroom->isHostOrCohost($user) || $user->hasAnyRole([Role::Admin->value, Role::SuperAdmin->value]),
            403,
        );
    }

    private function auditLive(Request $request, AuditAction $action, Classroom $classroom, LiveSession $session): void
    {
        Auditor::record(
            $action,
            $request->user(),
            $session,
            metadata: [
                'classroom_id' => $classroom->getKey(),
                'live_session_id' => $session->getKey(),
                'status' => $session->status->value,
            ],
            context: new AuditContext(
                portal: AuditPortal::Teach,
                sessionId: (string) $session->getKey(),
            ),
        );
    }
}
