<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use App\Models\User;
use App\Support\Enums\Permission;
use App\Support\Rbac\PermissionRegistry;

final class ImpliedViewPermissions
{
    /**
     * Standalone abilities that intentionally do not require resource.view
     * (e.g. reviewer queue uses question.flag without question.view).
     *
     * @var list<string>
     */
    private const EXEMPT = [
        Permission::QuestionFlag->value,
        // Lookup is the narrow alternative to user.view, not an extra action on top of it.
        'user.lookup',
        'user.update',
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

        // Lookup replaces the full user directory, so account actions do not
        // also require user.view.
        if ($view === 'user.view' && ($user->can('user.lookup') || $user->can('user.update'))) {
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
