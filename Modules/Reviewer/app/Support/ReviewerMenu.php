<?php

declare(strict_types=1);

namespace Modules\Reviewer\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

final class ReviewerMenu
{
    /** @return list<array{label: string, icon: string, route: string, match: string, permissions: list<string>, badge?: int}> */
    public static function for(?User $user, int $pendingCount = 0): array
    {
        if ($user === null) {
            return [];
        }

        $items = [
            ['label' => 'Tổng quan', 'icon' => 'dashboard', 'route' => 'reviewer.dashboard', 'match' => 'reviewer.dashboard', 'permissions' => ['reviewer_dashboard.view']],
            ['label' => 'Review câu hỏi', 'icon' => 'flag', 'route' => 'reviewer.questions.flags.index', 'match' => 'reviewer.questions.flags.*', 'permissions' => ['question_flag.view'], 'badge' => $pendingCount],
        ];

        return array_values(array_filter($items, static fn (array $item): bool => Route::has($item['route']) && $user->canAny($item['permissions'])));
    }
}
