<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Enums\Role;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Classroom\Actions\CreateClassroomAction;
use Modules\Classroom\Enums\ClassroomPurpose;
use Modules\Classroom\Enums\ClassroomVisibility;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Enums\MemberRole;
use Modules\Classroom\Enums\MemberStatus;
use Modules\Classroom\Http\Requests\StoreTeachClassroomRequest;
use Modules\Classroom\Models\Classroom;

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
            ->with(['host', 'liveSession'])
            ->withCount('activeMembers')
            ->with(['sessions' => fn ($query) => $query
                ->where('status', LiveSessionStatus::Scheduled->value)
                ->where('scheduled_at', '>', now())
                ->orderBy('scheduled_at')
                ->limit(1)])
            ->latest()
            ->get();

        $liveNow = $classrooms->filter(fn (Classroom $c): bool => $c->liveSession !== null)->count();
        $upcoming = $classrooms->filter(fn (Classroom $c): bool => $c->sessions->isNotEmpty())->count();

        return view('classroom::teach.classes.index', [
            'classrooms' => $classrooms,
            'stats' => [
                'total' => $classrooms->count(),
                'live' => $liveNow,
                'upcoming' => $upcoming,
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
            ->with('status', 'Đã tạo lớp chữa đề.');
    }

    public function show(Request $request, Classroom $classroom): View
    {
        $this->authorizeTeachClassroom($request, $classroom);

        $classroom->load(['host', 'activeMembers.user', 'sessions', 'liveSession']);

        return view('classroom::teach.classes.show', [
            'classroom' => $classroom,
            'upcomingSessions' => $classroom->sessions
                ->where('status', LiveSessionStatus::Scheduled)
                ->filter(fn ($session) => $session->scheduled_at === null || $session->scheduled_at->isFuture())
                ->take(5)
                ->values(),
            'pastSessions' => $classroom->sessions
                ->whereIn('status', [LiveSessionStatus::Ended, LiveSessionStatus::Cancelled])
                ->take(5)
                ->values(),
        ]);
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
}
