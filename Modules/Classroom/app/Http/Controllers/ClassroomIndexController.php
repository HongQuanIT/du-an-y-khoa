<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
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
        assert($user !== null);

        $filter = (string) $request->query('filter', '');

        $catalogRelations = ['host', 'liveSession', 'upcomingSession', 'replaySession'];

        $mine = $this->memberClassroomsQuery($user->getKey(), $catalogRelations)->get();

        $joinedIds = $mine->pluck('id')->all();

        $publicQuery = $this->publicClassroomsQuery($catalogRelations);
        $this->applyCatalogFilter($publicQuery, $filter);

        $public = $publicQuery->latest()->limit(24)->get();

        $liveNow = Classroom::query()
            ->with($catalogRelations)
            ->withCount('activeMembers')
            ->where('status', ClassroomStatus::Active)
            ->whereHas('sessions', fn (Builder $query) => $query->where('status', LiveSessionStatus::Live->value))
            ->get();

        $upcomingSoon = Classroom::query()
            ->with($catalogRelations)
            ->withCount('activeMembers')
            ->where('status', ClassroomStatus::Active)
            ->where(function (Builder $query) use ($user): void {
                $query->where('visibility', ClassroomVisibility::Public)
                    ->orWhereHas('members', function (Builder $memberQuery) use ($user): void {
                        $memberQuery->where('user_id', $user->getKey())
                            ->where('status', MemberStatus::Active->value);
                    });
            })
            ->whereHas('upcomingSession', fn (Builder $query) => $query
                ->where('scheduled_at', '<=', now()->addDays(7)))
            ->whereDoesntHave('sessions', fn (Builder $query) => $query->where('status', LiveSessionStatus::Live->value))
            ->get()
            ->sortBy(fn (Classroom $classroom) => $classroom->upcomingSession?->scheduled_at)
            ->take(12)
            ->values();

        return view('classroom::index', [
            'publicClassrooms' => $public,
            'myClassrooms' => $mine,
            'liveNow' => $liveNow,
            'upcomingSoon' => $upcomingSoon,
            'joinedClassroomIds' => $joinedIds,
            'filter' => $filter,
        ]);
    }

    /**
     * @param  list<string>  $relations
     * @return Builder<Classroom>
     */
    private function memberClassroomsQuery(int|string $userId, array $relations): Builder
    {
        return Classroom::query()
            ->with($relations)
            ->withCount('activeMembers')
            ->whereNot('status', ClassroomStatus::Archived)
            ->whereHas('members', function (Builder $query) use ($userId): void {
                $query->where('user_id', $userId)
                    ->where('status', MemberStatus::Active->value);
            })
            ->latest();
    }

    /**
     * @param  list<string>  $relations
     * @return Builder<Classroom>
     */
    private function publicClassroomsQuery(array $relations): Builder
    {
        return Classroom::query()
            ->with($relations)
            ->withCount('activeMembers')
            ->where('status', ClassroomStatus::Active)
            ->where('visibility', ClassroomVisibility::Public);
    }

    /** @param Builder<Classroom> $query */
    private function applyCatalogFilter(Builder $query, string $filter): void
    {
        match ($filter) {
            'live' => $query->whereHas(
                'sessions',
                fn (Builder $sessionQuery) => $sessionQuery->where('status', LiveSessionStatus::Live->value),
            ),
            'upcoming' => $query->whereHas('upcomingSession'),
            'recording' => $query->whereHas('replaySession'),
            default => null,
        };
    }
}
