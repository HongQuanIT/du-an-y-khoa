<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PlanPrice;

final class SavePlanPriceAction
{
    /**
     * @param  array{
     *     slug: string,
     *     label: string,
     *     price_cents: int,
     *     duration_days: ?int,
     *     billing_type: string,
     *     badge_label: ?string,
     *     savings_percent: ?int,
     *     cta_label: ?string,
     *     is_featured: bool,
     *     is_public: bool,
     *     sort_order: int,
     * }  $payload
     */
    public function handle(User $actor, Plan $plan, ?PlanPrice $price, array $payload): PlanPrice
    {
        unset($actor);

        if ($price === null) {
            $price = new PlanPrice(['plan_id' => $plan->getKey()]);
        }

        $price->fill(array_merge($payload, [
            'plan_id' => $plan->getKey(),
            'currency' => 'VND',
        ]))->save();

        return $price->fresh();
    }
}
