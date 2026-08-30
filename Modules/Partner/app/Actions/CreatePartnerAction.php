<?php

declare(strict_types=1);

namespace Modules\Partner\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Partner\Enums\PartnerStatus;
use Modules\Partner\Models\Partner;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

final class CreatePartnerAction
{
    use AsAction;

    /**
     * @param  array{
     *     user_id?: int|null,
     *     name?: string|null,
     *     email?: string|null,
     *     password?: string|null,
     *     display_name: string,
     *     default_commission_rate_bps: int,
     * }  $data
     */
    public function handle(array $data): Partner
    {
        return DB::transaction(function () use ($data): Partner {
            $user = null;

            if (! empty($data['user_id'])) {
                $user = User::query()->findOrFail((int) $data['user_id']);
                if (Partner::query()->where('user_id', $user->getKey())->exists()) {
                    throw ValidationException::withMessages([
                        'user_id' => 'Người dùng này đã là cộng tác viên.',
                    ]);
                }
            } else {
                $user = User::query()->create([
                    'name' => (string) $data['name'],
                    'email' => (string) $data['email'],
                    'password' => Hash::make((string) $data['password']),
                    'email_verified_at' => now(),
                    'locale' => app()->getLocale(),
                ]);
            }

            RoleModel::findOrCreate(Role::Partner->value, 'web');
            $user->syncRoles([Role::Partner->value]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return Partner::query()->create([
                'user_id' => $user->getKey(),
                'display_name' => $data['display_name'],
                'default_commission_rate_bps' => $data['default_commission_rate_bps'],
                'status' => PartnerStatus::Active,
            ]);
        });
    }
}
