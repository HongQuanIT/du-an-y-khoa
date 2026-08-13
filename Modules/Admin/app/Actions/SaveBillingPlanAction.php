<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use Modules\Billing\Models\Plan;

final class SaveBillingPlanAction
{
    /**
     * @param  array{
     *     name: string,
     *     description: ?string,
     *     features: list<string>,
     *     entitlements: list<string>,
     *     is_active: bool,
     *     sort_order: int,
     * }  $payload
     */
    public function handle(User $actor, Plan $plan, array $payload): Plan
    {
        unset($actor);

        $plan->fill([
            'name' => $payload['name'],
            'description' => $payload['description'],
            'features' => $payload['features'],
            'entitlements' => $payload['entitlements'],
            'is_active' => $payload['is_active'],
            'sort_order' => $payload['sort_order'],
        ])->save();

        return $plan->fresh(['prices']);
    }
}
