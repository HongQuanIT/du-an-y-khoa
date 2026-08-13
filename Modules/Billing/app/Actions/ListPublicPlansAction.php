<?php

declare(strict_types=1);

namespace Modules\Billing\Actions;

use Illuminate\Support\Collection;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PlanPrice;

final class ListPublicPlansAction
{
    /**
     * @return array{
     *     plans: Collection<int, Plan>,
     *     free: Plan|null,
     *     premium: Plan|null,
     *     yearlyPrices: Collection<int, PlanPrice>,
     *     monthlyPrice: PlanPrice|null,
     * }
     */
    public function handle(): array
    {
        $plans = Plan::query()
            ->active()
            ->ordered()
            ->with(['prices' => fn ($query) => $query->public()->ordered()])
            ->get();

        /** @var Plan|null $free */
        $free = $plans->first(fn (Plan $plan): bool => $plan->isFree());

        /** @var Plan|null $premium */
        $premium = $plans->first(fn (Plan $plan): bool => $plan->slug === 'premium');

        $yearlyPrices = $premium?->prices
            ->filter(fn (PlanPrice $price): bool => $price->billing_type === 'prepaid' && ($price->duration_days ?? 0) >= 365)
            ->values() ?? collect();

        $monthlyPrice = $premium?->prices
            ->first(fn (PlanPrice $price): bool => $price->billing_type === 'recurring' && $price->duration_days === 30);

        return [
            'plans' => $plans,
            'free' => $free,
            'premium' => $premium,
            'yearlyPrices' => $yearlyPrices,
            'monthlyPrice' => $monthlyPrice,
        ];
    }
}
