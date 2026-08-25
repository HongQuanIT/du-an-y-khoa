<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Role;
use Modules\Admin\Enums\AuditAction;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\AuditSnapshot;
use Modules\Admin\Support\StaffGuard;
use Spatie\Permission\PermissionRegistrar;

final class UpdateUserRoleAction
{
    use AsAction;

    public function handle(User $actor, User $target, Role $role): User
    {
        StaffGuard::assertCanManage($actor, $target);
        StaffGuard::assertCanAssignRole($actor, $role);

        $before = AuditSnapshot::user($target);

        $target->syncRoles([$role->value]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Auditor::record(
            AuditAction::UserRoleChanged,
            $actor,
            $target,
            $before,
            AuditSnapshot::user($target),
        );

        return $target->refresh();
    }
}
