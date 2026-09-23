<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Support\Enums\PortalGroup;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Landing page after authentication by portal audience.
 */
final class HomePath
{
    public static function for(?Authenticatable $user): string
    {
        return match (PortalAccess::primaryPortal($user)) {
            PortalGroup::Admin => route('admin.dashboard', absolute: false),
            PortalGroup::Instructor => route('teach.dashboard', absolute: false),
            PortalGroup::Partner => self::partnerPath($user),
            PortalGroup::Editor => route('editor.dashboard', absolute: false),
            PortalGroup::Learner => route('dashboard', absolute: false),
            null => route('landing.home', absolute: false),
        };
    }

    public static function partnerPath(Authenticatable $user): string
    {
        foreach ([
            'partner_dashboard.view' => 'partner.dashboard',
            'partner_code.view' => 'partner.codes.index',
            'partner_referral.view' => 'partner.referrals.index',
            'partner_commission.view' => 'partner.commissions.index',
            'partner_payout.view' => 'partner.payouts.index',
        ] as $permission => $route) {
            if ($user->can($permission)) {
                return route($route, absolute: false);
            }
        }

        return route('partner.dashboard', absolute: false);
    }
}
