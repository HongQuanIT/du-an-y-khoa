<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Instructor = role that uses the /teach portal (not learner, not admin CMS).
 */
final class Instructor
{
    public static function is(?Authenticatable $user): bool
    {
        return $user instanceof User && $user->hasRole(Role::Instructor->value);
    }
}
