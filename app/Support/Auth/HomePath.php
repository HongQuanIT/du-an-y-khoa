<?php

declare(strict_types=1);

namespace App\Support\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Landing page after authentication by portal audience.
 */
final class HomePath
{
    public static function for(?Authenticatable $user): string
    {
        if (Staff::isStaff($user)) {
            return route('admin.dashboard', absolute: false);
        }

        if (Instructor::is($user)) {
            return route('teach.dashboard', absolute: false);
        }

        return route('dashboard', absolute: false);
    }
}
