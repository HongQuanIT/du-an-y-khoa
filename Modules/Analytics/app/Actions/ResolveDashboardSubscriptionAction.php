<?php

declare(strict_types=1);

namespace Modules\Analytics\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PlanPrice;
use Modules\Billing\Support\CurrentSubscription;
use Modules\Billing\Support\MoneyFormatter;

final class ResolveDashboardSubscriptionAction
{
    use AsAction;

    /** @return array{show_upgrade: bool, plan_name: string, price_label: string|null, features: array<int, string>} */
    public function handle(User $user): array
    {
        $current = CurrentSubscription::for($user);

        if (! $current['is_free']) {
            return [
                'show_upgrade' => false,
                'plan_name' => $current['plan_name'],
                'price_label' => $current['price_label'],
                'features' => [],
            ];
        }

        $premium = Plan::query()->active()->where('slug', 'premium')->first();
        $monthly = $premium === null ? null : PlanPrice::query()
            ->where('plan_id', $premium->getKey())
            ->where('is_public', true)
            ->orderByRaw('CASE WHEN duration_days = 30 THEN 0 ELSE 1 END')
            ->orderBy('sort_order')
            ->first();

        return [
            'show_upgrade' => true,
            'plan_name' => $premium?->name ?? 'Premium',
            'price_label' => $monthly !== null
                ? MoneyFormatter::vnd($monthly->price_cents).'/'.$monthly->label
                : null,
            'features' => array_slice($premium?->features ?? [], 0, 3),
        ];
    }
}
