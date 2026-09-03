<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Admin\Actions\ApproveClassroomAction;
use Modules\Admin\Actions\ArchiveClassroomAction;
use Modules\Admin\Actions\ForceEndClassroomLiveAction;
use Modules\Admin\Actions\RejectClassroomAction;
use Modules\Classroom\Enums\ClassroomPurpose;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Models\Classroom;

final class ClassroomOversightController extends Controller
{
    public function create(): View
    {
        $this->authorizePermission(Permission::ClassroomOversee);

        return view('admin::classrooms.create', [
            'instructors' => User::role(\App\Support\Enums\Role::Instructor->value)->orderBy('name')->get(['id', 'name', 'email']),
            'purposes' => ClassroomPurpose::teachCases(),
            'visibilities' => \Modules\Classroom\Enums\ClassroomVisibility::cases(),
        ]);
    }

    public function store(
        Request $request,
        \Modules\Classroom\Actions\CreateClassroomAction $create,
        ApproveClassroomAction $approve,
    ): RedirectResponse {
        $this->authorizePermission(Permission::ClassroomOversee);
        $data = $request->validate([
            'host_user_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'purpose' => ['required', \Illuminate\Validation\Rule::in(array_map(fn (ClassroomPurpose $p) => $p->value, ClassroomPurpose::teachCases()))],
            'visibility' => ['required', \Illuminate\Validation\Rule::enum(\Modules\Classroom\Enums\ClassroomVisibility::class)],
            'max_members' => ['nullable', 'integer', 'min:2', 'max:5000'],
        ]);
        $host = User::findOrFail($data['host_user_id']);
        abort_unless($host->hasRole(\App\Support\Enums\Role::Instructor->value), 422, 'Host phải là giảng viên.');

        // Lớp do chính Admin tạo và duyệt ngay không cần thông báo chờ duyệt cho các Admin khác.
        $classroom = $create->handle($host, $data, notifyAdmins: false);
        $approve->handle($this->actor(), $classroom);

        return redirect()->route('admin.classrooms.show', $classroom)->with('status', 'Đã tạo lớp cho giảng viên.');
    }

    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::ClassroomOversee);

        $query = Classroom::query()
            ->with(['host', 'liveSession'])
            ->withCount([
                'activeMembers',
                'sessions as live_sessions_count' => fn ($q) => $q->where('status', LiveSessionStatus::Live->value),
            ])
            ->latest('id');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('join_code', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', (string) $status);
        }

        if ($purpose = $request->query('purpose')) {
            $query->where('purpose', (string) $purpose);
        }

        if ($hostId = $request->query('host_id')) {
            $query->where('host_user_id', (int) $hostId);
        }

        $classrooms = $query->paginate(20)->withQueryString();

        $pendingCount = Classroom::query()
            ->where('status', ClassroomStatus::PendingApproval)
            ->count();

        return view('admin::classrooms.index', [
            'classrooms' => $classrooms,
            'pendingCount' => $pendingCount,
            'statuses' => ClassroomStatus::cases(),
            'purposes' => ClassroomPurpose::cases(),
            'filters' => [
                'q' => $search,
                'status' => $request->query('status'),
                'purpose' => $request->query('purpose'),
                'host_id' => $request->query('host_id'),
            ],
        ]);
    }

    public function show(Classroom $classroom): View
    {
        $this->authorizePermission(Permission::ClassroomOversee);

        $classroom->load([
            'host',
            'activeMembers.user',
            'sessions' => fn ($query) => $query
                ->with('recordings')
                ->latest('scheduled_at')
                ->limit(20),
        ]);

        return view('admin::classrooms.show', [
            'classroom' => $classroom,
        ]);
    }

    public function scheduleLive(
        \Modules\Classroom\Http\Requests\ScheduleSessionRequest $request,
        Classroom $classroom,
        \Modules\Classroom\Actions\ScheduleLiveSessionAction $action,
    ): RedirectResponse {
        $this->authorizePermission(Permission::ClassroomOversee);
        $session = $action->handle($classroom, $request->sessionPayload());

        return back()->with('status', 'Đã tạo phòng live: '.$session->title);
    }

    public function forceEnd(Classroom $classroom, ForceEndClassroomLiveAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::ClassroomOversee);

        $ended = $action->handle($this->actor(), $classroom);

        if ($ended === null) {
            return back()->with('status', 'Lớp không có buổi live đang chạy.');
        }

        return back()->with('status', 'Đã force-end buổi live.');
    }

    public function approve(Classroom $classroom, ApproveClassroomAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::ClassroomOversee);

        $action->handle($this->actor(), $classroom);

        return back()->with('status', 'Đã duyệt lớp — hiển thị cho học viên.');
    }

    public function reject(Classroom $classroom, RejectClassroomAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::ClassroomOversee);

        $action->handle($this->actor(), $classroom);

        return back()->with('status', 'Đã từ chối lớp học.');
    }

    public function archive(Classroom $classroom, ArchiveClassroomAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::ClassroomOversee);

        $action->handle($this->actor(), $classroom);

        return back()->with('status', 'Đã lưu trữ lớp học.');
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
