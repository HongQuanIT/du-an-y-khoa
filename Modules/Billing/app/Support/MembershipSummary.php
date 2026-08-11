<?php

declare(strict_types=1);

namespace Modules\Billing\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Billing\Models\Subscription;

final class MembershipSummary
{
    /**
     * @return array{plan_name: string, description: string, ends_at: Carbon|null, source: string|null}
     */
    public static function for(User $user): array
    {
        /** @var Subscription|null $subscription */
        $subscription = Subscription::query()
            ->with('plan')
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', Carbon::now());
            })
            ->orderByDesc('starts_at')
            ->first();

        if ($subscription?->plan === null) {
            return [
                'plan_name' => 'Free',
                'description' => 'Quyền truy cập cơ bản',
                'ends_at' => null,
                'source' => null,
            ];
        }

        $description = $subscription->plan->description ?? 'Gói đang hoạt động';
        if ($subscription->ends_at !== null) {
            $description .= ' — hết hạn '.$subscription->ends_at->locale('vi')->isoFormat('D MMMM YYYY');
        }

        return [
            'plan_name' => $subscription->plan->name,
            'description' => $description,
            'ends_at' => $subscription->ends_at,
            'source' => $subscription->source,
        ];
    }
}
