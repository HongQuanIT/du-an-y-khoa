<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Landing page after authentication: staff go to the admin panel, learners to
 * the student dashboard. Shared by the login flow and the `guest` middleware.
 */
final class HomePath
{
    public static function for(?Authenticatable $user): string
    {
        $staffRoles = [Role::Admin->value, Role::SuperAdmin->value, Role::ContentEditor->value];

        if ($user instanceof User && $user->hasAnyRole($staffRoles)) {
            return route('admin.dashboard', absolute: false);
        }

        return route('dashboard', absolute: false);
    }
}
