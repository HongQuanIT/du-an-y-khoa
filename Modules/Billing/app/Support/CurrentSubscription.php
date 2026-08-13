<?php

declare(strict_types=1);

namespace Modules\Billing\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Support\EntitlementLabels;

final class CurrentSubscription
{
    /**
     * @return array{
     *     plan_id: int|null,
     *     plan_slug: string,
     *     plan_name: string,
     *     plan_price_id: int|null,
     *     price_label: string|null,
     *     description: string,
     *     starts_at: Carbon|null,
     *     ends_at: Carbon|null,
     *     source: string|null,
     *     status: string|null,
     *     is_free: bool,
     *     entitlements: list<string>,
     *     entitlement_labels: list<string>,
     * }
     */
    public static function for(?User $user): array
    {
        if ($user === null) {
            return self::freeDefaults();
        }

        /** @var Subscription|null $subscription */
        $subscription = Subscription::query()
            ->with(['plan', 'planPrice'])
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', Carbon::now());
            })
            ->orderByDesc('starts_at')
            ->first();

        if ($subscription?->plan === null) {
            return self::freeDefaults();
        }

        $plan = $subscription->plan;
        $description = $plan->description ?? 'Gói đang hoạt động';

        if ($subscription->ends_at !== null) {
            $description .= ' — hết hạn '.$subscription->ends_at->locale('vi')->isoFormat('D MMMM YYYY');
        }

        $entitlements = $plan->entitlements ?? [];

        return [
            'plan_id' => $plan->id,
            'plan_slug' => $plan->slug,
            'plan_name' => $plan->name,
            'plan_price_id' => $subscription->plan_price_id,
            'price_label' => $subscription->planPrice?->label,
            'description' => $description,
            'starts_at' => $subscription->starts_at,
            'ends_at' => $subscription->ends_at,
            'source' => $subscription->source,
            'status' => $subscription->status,
            'is_free' => $plan->isFree(),
            'entitlements' => $entitlements,
            'entitlement_labels' => EntitlementLabels::labels($entitlements),
        ];
    }

    /** @return array<string, mixed> */
    private static function freeDefaults(): array
    {
        return [
            'plan_id' => null,
            'plan_slug' => 'free',
            'plan_name' => 'Free',
            'plan_price_id' => null,
            'price_label' => null,
            'description' => 'Quyền truy cập cơ bản',
            'starts_at' => null,
            'ends_at' => null,
            'source' => null,
            'status' => null,
            'is_free' => true,
            'entitlements' => [],
            'entitlement_labels' => [],
        ];
    }
}
