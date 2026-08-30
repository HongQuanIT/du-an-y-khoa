<?php

declare(strict_types=1);

namespace Modules\Partner\Database\Seeders;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Partner\Enums\PartnerStatus;
use Modules\Partner\Models\Partner;
use Modules\Partner\Models\PartnerInviteCode;
use Spatie\Permission\Models\Role as RoleModel;

class PartnerDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        RoleModel::findOrCreate(Role::Partner->value, 'web');

        $user = User::firstOrCreate(
            ['email' => 'partner@medlearn.local'],
            [
                'name' => 'Cộng tác viên Demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'locale' => 'vi',
            ],
        );

        $user->forceFill([
            'name' => 'Cộng tác viên Demo',
            'password' => Hash::make('password'),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $user->syncRoles([Role::Partner->value]);

        $partner = Partner::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            [
                'display_name' => 'CTV Demo',
                'default_commission_rate_bps' => 1000,
                'status' => PartnerStatus::Active,
            ],
        );

        PartnerInviteCode::query()->updateOrCreate(
            ['code' => 'CTVDEMO'],
            [
                'partner_id' => $partner->getKey(),
                'label' => 'Mã demo',
                'starts_at' => null,
                'expires_at' => now()->addYear(),
                'max_uses' => null,
                'commission_rate_bps' => null,
                'is_active' => true,
                'use_count' => 0,
            ],
        );
    }
}
