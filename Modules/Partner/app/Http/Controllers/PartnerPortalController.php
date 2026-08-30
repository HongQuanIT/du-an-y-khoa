<?php

declare(strict_types=1);

namespace Modules\Partner\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Partner\Enums\CommissionStatus;
use Modules\Partner\Models\Partner;
use Modules\Partner\Models\PartnerCommission;
use RuntimeException;

abstract class PartnerPortalController extends Controller
{
    protected function partner(Request $request): Partner
    {
        /** @var User $user */
        $user = $request->user();
        $partner = Partner::forUser($user);

        if ($partner === null) {
            throw new RuntimeException('Partner profile missing for user '.$user->getKey());
        }

        return $partner;
    }

    protected function dashboardStats(Partner $partner): array
    {
        $referralCount = $partner->attributions()->count();

        $pendingCents = (int) PartnerCommission::query()
            ->where('partner_id', $partner->getKey())
            ->where('status', CommissionStatus::Pending)
            ->sum('commission_cents');

        $paidCents = (int) PartnerCommission::query()
            ->where('partner_id', $partner->getKey())
            ->where('status', CommissionStatus::Paid)
            ->sum('commission_cents');

        $approvedCents = (int) PartnerCommission::query()
            ->where('partner_id', $partner->getKey())
            ->where('status', CommissionStatus::Approved)
            ->sum('commission_cents');

        return [
            'referral_count' => $referralCount,
            'pending_cents' => $pendingCents,
            'approved_cents' => $approvedCents,
            'paid_cents' => $paidCents,
        ];
    }
}
