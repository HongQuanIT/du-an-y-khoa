<?php

declare(strict_types=1);

namespace Modules\Billing\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Modules\Billing\Models\InstitutionMember;
use Modules\Billing\Models\Subscription;

/**
 * Merge entitlements from active subscriptions and verified institution licenses.
 */
final class ResolveUserEntitlementsAction
{
    use AsAction;

    /** @return list<string> */
    public function handle(User $user): array
    {
        $entitlements = [];

        Subscription::query()
            ->with('plan')
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', Carbon::now());
            })
            ->get()
            ->each(function (Subscription $subscription) use (&$entitlements): void {
                foreach ($subscription->plan?->entitlements ?? [] as $entitlement) {
                    if (is_string($entitlement) && $entitlement !== '') {
                        $entitlements[] = $entitlement;
                    }
                }
            });

        InstitutionMember::query()
            ->with(['institution.plan'])
            ->where('user_id', $user->getKey())
            ->where('status', 'verified')
            ->get()
            ->each(function (InstitutionMember $member) use (&$entitlements): void {
                $institution = $member->institution;
                if ($institution === null || ! $institution->isValid()) {
                    return;
                }

                foreach ($institution->plan?->entitlements ?? [] as $entitlement) {
                    if (is_string($entitlement) && $entitlement !== '') {
                        $entitlements[] = $entitlement;
                    }
                }
            });

        return array_values(array_unique($entitlements));
    }
}
