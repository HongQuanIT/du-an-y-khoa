<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Partner (CTV) = role that uses the /partner portal.
 */
final class Partner
{
    public static function is(?Authenticatable $user): bool
    {
        return $user instanceof User && $user->hasRole(Role::Partner->value);
    }
}
