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
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;

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
        ]);
        $search = trim((string) ($validated['q'] ?? ''));

        // Chỉ trả về câu đã xuất bản và đúng quyền QBank của giảng viên.
        $questions = Question::query()
            ->with(['medicalTaxonomyNodes:id,name'])
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
                            'medicalTaxonomyNodes',
                            fn ($nodes) => $nodes->whereRaw("name LIKE ? ESCAPE '!'", [$pattern]),
                        );
                });
            })
            ->latest()
            ->limit(30)
            ->get(['id', 'stem', 'difficulty']);

        return ApiResponse::item([
            'questions' => $questions->map(fn (Question $question): array => [
                'id' => (string) $question->getKey(),
                'stem' => trim(strip_tags(html_entity_decode($question->stem, ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
                'difficulty' => $question->difficulty->label(),
                'topic' => $question->medicalTaxonomyNodes->pluck('name')->join(', ') ?: 'Tổng hợp',
                'topics' => $question->medicalTaxonomyNodes->pluck('name')->values(),
                'medical_taxonomy_node_ids' => $question->medicalTaxonomyNodes->pluck('id')->map(fn ($id): int => (int) $id)->values(),
                'topic_ids' => $question->medicalTaxonomyNodes->pluck('id')->map(fn ($id): int => (int) $id)->values(),
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
