<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'name' => 'notification.view',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $studentRoleId = DB::table('roles')
            ->where('name', 'student')
            ->where('guard_name', 'web')
            ->value('id');

        if ($studentRoleId === null) {
            return;
        }

        $permissionId = DB::table('permissions')
            ->where('name', 'notification.view')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId === null) {
            return;
        }

        DB::table('role_has_permissions')->insertOrIgnore([
            'permission_id' => $permissionId,
            'role_id' => $studentRoleId,
        ]);
    }

    public function down(): void {}
};
