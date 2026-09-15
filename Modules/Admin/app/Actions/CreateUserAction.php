<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\PortalGroup;
use App\Support\Enums\UserStatus;
use Illuminate\Support\Facades\DB;
use Modules\Admin\Enums\AuditAction;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\AuditSnapshot;
use Modules\Admin\Support\StaffGuard;
use Modules\Partner\Actions\EnsurePartnerProfileAction;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

final class CreateUserAction
{
    use AsAction;

    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function handle(User $actor, array $data, RoleModel $role): User
    {
        StaffGuard::assertCanAssignRole($actor, $role);

        return DB::transaction(function () use ($actor, $data, $role): User {
            $user = User::query()->forceCreate([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'email_verified_at' => now(),
                'locale' => app()->getLocale(),
                'status' => UserStatus::Active,
            ]);

            $user->syncRoles([$role]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            if ($role->portal === PortalGroup::Partner->value) {
                app(EnsurePartnerProfileAction::class)->handle($user);
            }

            Auditor::record(
                AuditAction::UserCreated,
                $actor,
                $user,
                null,
                AuditSnapshot::user($user),
            );

            return $user->refresh();
        });
    }
}
