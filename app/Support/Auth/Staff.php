<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Staff = roles that use the admin portal (same web guard/session as learners).
 */
final class Staff
{
    /**
     * @return list<string>
     */
    public static function roleValues(): array
    {
        return [
            Role::Admin->value,
            Role::SuperAdmin->value,
            Role::ContentEditor->value,
        ];
    }

    public static function isStaff(?Authenticatable $user): bool
    {
        return $user instanceof User && $user->hasAnyRole(self::roleValues());
    }
}
