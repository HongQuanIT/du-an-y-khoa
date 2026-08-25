<?php

declare(strict_types=1);

namespace Modules\Auth\Actions;

use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Role;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Data\RegisterData;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Use case: create a self-service learner account.
 *
 * Logging the new user in stays in the HTTP layer; this action owns the user
 * record and its default role.
 */
final class RegisterUserAction
{
    use AsAction;

    public function handle(RegisterData $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password,
                'locale' => app()->getLocale(),
            ]);

            RoleModel::findOrCreate(Role::Student->value, 'web');
            $user->assignRole(Role::Student->value);

            return $user;
        });

        event(new Registered($user));
        Auditor::record(AuditAction::AuthRegistered, $user, $user);

        return $user;
    }
}
