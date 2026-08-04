<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\StudyPlan\Enums\PlanStatus;
use Modules\StudyPlan\Models\StudyPlan;

/**
 * Read paths shared by the overview page, dashboard widget and API.
 */
final class StudyPlanRepository
{
    /**
     * The plan the learner is working on: the active one, otherwise the most
     * recent (so a finished plan is still reachable).
     */
    public function currentFor(?User $user): ?StudyPlan
    {
        if ($user === null) {
            return null;
        }

        return StudyPlan::query()
            ->where('user_id', $user->getKey())
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [PlanStatus::Active->value])
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * @return LengthAwarePaginator<int, StudyPlan>
     */
    public function paginateFor(User $user, int $perPage = 6): LengthAwarePaginator
    {
        return StudyPlan::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
