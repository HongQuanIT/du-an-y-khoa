<?php

declare(strict_types=1);

namespace Modules\Partner\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Partner portal: read-only list of invite codes assigned by Admin.
 */
final class PartnerCodeController extends PartnerPortalController
{
    public function index(Request $request): View
    {
        $partner = $this->partner($request);

        $codes = $partner->inviteCodes()
            ->latest('id')
            ->paginate(20);

        return view('partner::codes.index', [
            'partner' => $partner,
            'codes' => $codes,
        ]);
    }
}
