<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\QuestionBank\Database\Seeders\QuestionBankDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $this->seedBaseUsers();

        // Learning slice: topics, questions/options, sessions/attempts/status.
        $this->call(QuestionBankDatabaseSeeder::class);
    }

    /**
     * Fixed accounts for local dev / QA (password: "password").
     */
    private function seedBaseUsers(): void
    {
        $accounts = [
            ['Super Admin', 'admin@medlearn.local', Role::SuperAdmin],
            ['Content Editor', 'editor@medlearn.local', Role::ContentEditor],
            ['Student', 'student@medlearn.local', Role::Student],
            ['Student Two', 'student2@medlearn.local', Role::Student],
            ['Student Three', 'student3@medlearn.local', Role::Student],
        ];

        foreach ($accounts as [$name, $email, $role]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );

            if (! $user->hasRole($role->value)) {
                $user->assignRole($role->value);
            }
        }
    }
}
