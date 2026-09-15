<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
use App\Support\Enums\PortalGroup;
use App\Support\Enums\Role as SystemRole;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Resolves portal membership from the assigned Spatie role metadata.
 *
 * Role names identify protected system roles, while the `roles.portal` column
 * is the source of truth for portal access. This keeps custom roles usable.
 */
final class PortalAccess
{
    public static function allows(?Authenticatable $user, PortalGroup $portal): bool
    {
        return self::portalsFor($user)->contains($portal);
    }

    public static function primaryPortal(?Authenticatable $user): ?PortalGroup
    {
        return self::portalsFor($user)->first();
    }

    /**
     * Role names belonging to a portal, including legacy system roles whose
     * portal metadata may not have been backfilled yet.
     *
     * @return list<string>
     */
    public static function roleNames(PortalGroup $portal): array
    {
        $legacyNames = collect(SystemRole::cases())
            ->filter(fn (SystemRole $role): bool => $role->portal() === $portal)
            ->map(fn (SystemRole $role): string => $role->value)
            ->all();

        return RoleModel::query()
            ->where('guard_name', 'web')
            ->where(function ($query) use ($portal, $legacyNames): void {
                $query->where('portal', $portal->value)
                    ->orWhereIn('name', $legacyNames);
            })
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, PortalGroup>
     */
    public static function portalsFor(?Authenticatable $user): Collection
    {
        if (! $user instanceof User) {
            return collect();
        }

        $roles = $user->relationLoaded('roles')
            ? $user->getRelation('roles')
            : $user->roles()->get();

        return $roles
            ->map(function ($role): ?PortalGroup {
                $portal = PortalGroup::tryFrom((string) ($role->portal ?? ''));

                if ($portal !== null) {
                    return $portal;
                }

                // Compatibility for system roles created before the portal migration.
                return SystemRole::tryFrom((string) $role->name)?->portal();
            })
            ->filter()
            ->unique(fn (PortalGroup $portal): string => $portal->value)
            ->values();
    }
}
