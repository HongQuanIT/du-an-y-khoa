<?php

declare(strict_types=1);

namespace Modules\Partner\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Billing\Support\CurrentSubscription;

final class PartnerReferralController extends PartnerPortalController
{
    public function index(Request $request): View
    {
        $partner = $this->partner($request);

        $attributions = $partner->attributions()
            ->with(['referredUser', 'inviteCode'])
            ->latest('attributed_at')
            ->paginate(20);

        $rows = $attributions->getCollection()->map(function ($attribution) {
            $user = $attribution->referredUser;
            $sub = CurrentSubscription::for($user);

            return [
                'attribution' => $attribution,
                'user' => $user,
                'plan_name' => $sub['plan_name'],
                'plan_slug' => $sub['plan_slug'],
                'status' => $sub['status'] ?? ($sub['is_free'] ? 'free' : null),
                'ends_at' => $sub['ends_at'],
            ];
        });

        $attributions->setCollection($rows);

        return view('partner::referrals.index', [
            'partner' => $partner,
            'referrals' => $attributions,
        ]);
    }
}
