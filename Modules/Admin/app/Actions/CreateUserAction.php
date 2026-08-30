<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Role;
use App\Support\Enums\UserStatus;
use Modules\Admin\Enums\AuditAction;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\AuditSnapshot;
use Modules\Admin\Support\StaffGuard;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

final class CreateUserAction
{
    use AsAction;

    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function handle(User $actor, array $data, Role $role): User
    {
        StaffGuard::assertCanAssignRole($actor, $role);

        RoleModel::findOrCreate($role->value, 'web');

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'email_verified_at' => now(),
            'locale' => app()->getLocale(),
            'status' => UserStatus::Active,
        ]);

        $user->syncRoles([$role->value]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Auditor::record(
            AuditAction::UserCreated,
            $actor,
            $user,
            null,
            AuditSnapshot::user($user),
        );

        return $user->refresh();
    }
}
