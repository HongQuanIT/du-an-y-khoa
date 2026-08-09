<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Role;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\StaffGuard;
use Spatie\Permission\PermissionRegistrar;

final class UpdateUserRoleAction
{
    use AsAction;

    public function handle(User $actor, User $target, Role $role): User
    {
        StaffGuard::assertCanManage($actor, $target);
        StaffGuard::assertCanAssignRole($actor, $role);

        $before = ['role' => $target->primaryRoleName()];

        $target->syncRoles([$role->value]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Auditor::record(
            'admin.user.role_change',
            $actor,
            $target,
            $before,
            ['role' => $role->value],
        );

        return $target->refresh();
    }
}
