<?php

declare(strict_types=1);

namespace Modules\Billing\Support;

use App\Models\User;

final class MembershipSummary
{
    /**
     * @return array{plan_name: string, description: string, ends_at: \Illuminate\Support\Carbon|null, source: string|null}
     */
    public static function for(User $user): array
    {
        $current = CurrentSubscription::for($user);

        return [
            'plan_name' => $current['plan_name'],
            'description' => $current['description'],
            'ends_at' => $current['ends_at'],
            'source' => $current['source'],
        ];
    }
}

