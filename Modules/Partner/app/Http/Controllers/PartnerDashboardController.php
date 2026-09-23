<?php

declare(strict_types=1);

namespace Modules\Partner\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Partner\Actions\GetPartnerDashboardDataAction;

final class PartnerDashboardController extends PartnerPortalController
{
    public function index(Request $request, GetPartnerDashboardDataAction $dashboard): View
    {
        $partner = $this->partner($request);

        /** @var User $viewer */
        $viewer = $request->user();

        return view('partner::dashboard', [
            'partner' => $partner,
            ...$dashboard->handle($viewer, $partner),
        ]);
    }
}
