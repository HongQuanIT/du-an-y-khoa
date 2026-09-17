<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $adminRoleId = DB::table('roles')
            ->where('name', 'admin')
            ->where('guard_name', 'web')
            ->value('id');
        $approvePermissionId = DB::table('permissions')
            ->where('name', 'question.approve')
            ->where('guard_name', 'web')
            ->value('id');

        if ($adminRoleId !== null && $approvePermissionId !== null) {
            DB::table('role_has_permissions')
                ->where('role_id', $adminRoleId)
                ->where('permission_id', $approvePermissionId)
                ->delete();
        }
    }

    public function down(): void {}
};
