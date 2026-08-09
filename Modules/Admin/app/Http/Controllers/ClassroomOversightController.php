<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Admin\Actions\ArchiveClassroomAction;
use Modules\Admin\Actions\ForceEndClassroomLiveAction;
use Modules\Classroom\Enums\ClassroomPurpose;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Models\Classroom;

final class ClassroomOversightController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::ClassroomOversee);

        $query = Classroom::query()
            ->with(['host'])
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

        return view('admin::classrooms.index', [
            'classrooms' => $classrooms,
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

    public function forceEnd(Classroom $classroom, ForceEndClassroomLiveAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::ClassroomOversee);

        $ended = $action->handle($this->actor(), $classroom);

        if ($ended === null) {
            return back()->with('status', 'Lớp không có buổi live đang chạy.');
        }

        return back()->with('status', 'Đã force-end buổi live.');
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
