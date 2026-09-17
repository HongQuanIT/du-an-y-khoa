<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $names = [
            'question.view',
            'question.approve',
            'question.reject',
        ];

        foreach ($names as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $superAdminRoleId = DB::table('roles')
            ->where('name', 'super_admin')
            ->where('guard_name', 'web')
            ->value('id');

        if ($superAdminRoleId === null) {
            return;
        }

        foreach (DB::table('permissions')->whereIn('name', $names)->pluck('id') as $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $superAdminRoleId,
            ]);
        }
    }

    public function down(): void {}
};
