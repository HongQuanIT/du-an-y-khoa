<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Enums\Entitlement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\ClassroomVisibility;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Enums\MemberStatus;
use Modules\Classroom\Models\Classroom;

final class ClassroomIndexController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', Classroom::class);

        $user = $request->user();
        $filter = $request->query('filter');

        $publicQuery = Classroom::query()
            ->with(['host', 'liveSession'])
            ->withCount('activeMembers')
            ->where('status', ClassroomStatus::Active)
            ->where('visibility', ClassroomVisibility::Public);

        if ($filter === 'live') {
            $publicQuery->whereHas('sessions', fn ($q) => $q->where('status', LiveSessionStatus::Live->value));
        }

        $public = $publicQuery->latest()->limit(24)->get();

        $mine = Classroom::query()
            ->with(['host', 'liveSession'])
            ->withCount('activeMembers')
            ->where('status', ClassroomStatus::Active)
            ->whereHas('members', function ($q) use ($user): void {
                $q->where('user_id', $user->getKey())
                    ->where('status', MemberStatus::Active->value);
            })
            ->latest()
            ->get();

        $liveNow = Classroom::query()
            ->with(['host', 'liveSession'])
            ->withCount('activeMembers')
            ->where('status', ClassroomStatus::Active)
            ->whereHas('sessions', fn ($q) => $q->where('status', LiveSessionStatus::Live->value))
            ->get();

        return view('classroom::index', [
            'publicClassrooms' => $public,
            'myClassrooms' => $mine,
            'liveNow' => $liveNow,
            'canHost' => $user->hasEntitlement(Entitlement::ClassroomHost->value),
            'filter' => $filter,
        ]);
    }
}
