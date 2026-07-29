<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@medlearn.local',
        ])->assignRole(Role::SuperAdmin->value);

        User::factory()->create([
            'name' => 'Student',
            'email' => 'student@medlearn.local',
        ])->assignRole(Role::Student->value);
    }
}
