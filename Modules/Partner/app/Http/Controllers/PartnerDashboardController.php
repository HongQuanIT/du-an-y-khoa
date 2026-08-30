<?php

declare(strict_types=1);

namespace Modules\Partner\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

final class PartnerDashboardController extends PartnerPortalController
{
    public function index(Request $request): View
    {
        $partner = $this->partner($request);

        return view('partner::dashboard', [
            'partner' => $partner,
            'stats' => $this->dashboardStats($partner),
        ]);
    }
}
