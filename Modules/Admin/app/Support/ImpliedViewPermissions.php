<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use App\Models\User;
use App\Support\Rbac\PermissionRegistry;

final class ImpliedViewPermissions
{
    /** @var array<string, string|null>|null */
    private static ?array $cache = null;

    public static function missingFor(User $user, string $permission): ?string
    {
        $view = self::viewPermissionFor($permission);

        if ($view === null || $permission === $view || ! $user->can($permission)) {
            return null;
        }

        return $user->can($view) ? null : $view;
    }

    private static function viewPermissionFor(string $permission): ?string
    {
        if (self::$cache === null) {
            self::$cache = [];

            foreach (app(PermissionRegistry::class)->all() as $definition) {
                [$resource, $action] = array_pad(explode('.', $definition->name, 2), 2, '');

                if ($action === 'view') {
                    self::$cache[$resource] = $definition->name;
                }
            }
        }

        [$resource] = array_pad(explode('.', $permission, 2), 2, '');

        return self::$cache[$resource] ?? null;
    }
}
