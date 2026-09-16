<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $superAdminRoleId = DB::table('roles')
            ->where('name', 'super_admin')
            ->where('guard_name', 'web')
            ->value('id');

        if ($superAdminRoleId === null) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'learning_tool.flag',
                'learning_tool.highlight',
                'learning_tool.note',
                'learning_tool.research',
            ])
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $superAdminRoleId,
            ]);
        }
    }

    public function down(): void {}
};
