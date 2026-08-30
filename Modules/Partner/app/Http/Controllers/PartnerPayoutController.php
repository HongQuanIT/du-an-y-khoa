<?php

declare(strict_types=1);

namespace Modules\Partner\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Partner\Models\PartnerPayout;

final class PartnerPayoutController extends PartnerPortalController
{
    public function index(Request $request): View
    {
        $partner = $this->partner($request);

        $payouts = PartnerPayout::query()
            ->where('partner_id', $partner->getKey())
            ->latest('id')
            ->paginate(20);

        return view('partner::payouts.index', [
            'partner' => $partner,
            'payouts' => $payouts,
        ]);
    }
}
