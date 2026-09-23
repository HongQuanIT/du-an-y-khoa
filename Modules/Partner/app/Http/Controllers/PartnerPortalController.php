<?php

declare(strict_types=1);

namespace Modules\Partner\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Modules\Partner\Actions\EnsurePartnerProfileAction;
use Modules\Partner\Models\Partner;

abstract class PartnerPortalController extends Controller
{
    protected function partner(Request $request): Partner
    {
        /** @var User $user */
        $user = $request->user();

        return Partner::forUser($user)
            ?? app(EnsurePartnerProfileAction::class)->handle($user);
    }
}
