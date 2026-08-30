<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Fixed accounts for local dev / QA.
 *
 * Run on its own with: php artisan db:seed --class=UserSeeder
 * Requires RolePermissionSeeder to have provisioned the roles first.
 */
class UserSeeder extends Seeder
{
    /**
     * @var list<array{string, string, Role}>
     */
    private const ACCOUNTS = [
        ['Super Admin', 'superadmin@medlearn.local', Role::SuperAdmin, 'SuperAdmin123!'],
        ['Content Editor', 'editor@medlearn.local', Role::ContentEditor],

        ['Giảng viên Minh', 'instructor@medlearn.local', Role::Instructor],
        ['Cộng tác viên Demo', 'partner@medlearn.local', Role::Partner],
        ['Nguyễn Văn An', 'student@medlearn.local', Role::Student],
        ['Trần Thị Bình', 'student2@medlearn.local', Role::Student],
        ['Lê Hoàng Cường', 'student3@medlearn.local', Role::Student],
    ];

    public function run(): void
    {
        foreach (self::ACCOUNTS as $account) {
            [$name, $email, $role] = $account;
            $password = $account[3] ?? 'password';

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                ],
            );

            $user->forceFill([
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            if (! $user->hasRole($role->value)) {
                $user->assignRole($role->value);
            }
        }
    }
}
