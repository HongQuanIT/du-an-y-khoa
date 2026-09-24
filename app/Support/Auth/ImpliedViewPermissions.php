<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
use App\Support\Enums\Permission;
use App\Support\Rbac\PermissionRegistry;

final class ImpliedViewPermissions
{
    /** @var list<string> */
    private const EXEMPT = [
        Permission::QuestionFlag->value,
    ];

    /** @var array<string, string|null>|null */
    private static ?array $cache = null;

    public static function missingFor(User $user, string $permission): ?string
    {
        if (in_array($permission, self::EXEMPT, true)) {
            return null;
        }

        $view = self::viewPermissionFor($permission);

        if ($view === null || $permission === $view || ! $user->can($permission)) {
            return null;
        }

        return $user->can($view) ? null : $view;
    }

    private static function viewPermissionFor(string $permission): ?string
    {
        if ($permission === 'question.flag') {
            return 'question_flag.view';
        }

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
