<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use App\Models\User;
use App\Support\Auth\PortalRouteAccess;

/** Keeps links and forms aligned with the target route's permission checks. */
final class AdminRouteAccess
{
    public static function allows(?User $user, string $name): bool
    {
        return PortalRouteAccess::allows($user, $name);
    }
}
