<?php

declare(strict_types=1);

namespace Modules\Partner\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Partner\Models\PartnerCommission;

final class PartnerCommissionController extends PartnerPortalController
{
    public function index(Request $request): View
    {
        $partner = $this->partner($request);

        $commissions = PartnerCommission::query()
            ->where('partner_id', $partner->getKey())
            ->with(['referredUser', 'payment'])
            ->latest('id')
            ->paginate(20);

        return view('partner::commissions.index', [
            'partner' => $partner,
            'commissions' => $commissions,
        ]);
    }
}
