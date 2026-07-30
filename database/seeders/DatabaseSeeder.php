<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\QuestionBank\Database\Seeders\QuestionBankDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        // Accounts you log in with during development.
        $this->call(UserSeeder::class);

        // Learning slice: topics, questions/options, sessions/attempts/status.
        $this->call(QuestionBankDatabaseSeeder::class);

        $this->command->info('Đăng nhập dev: student@medlearn.local / password (admin@medlearn.local cho khu vực admin).');
    }
}
