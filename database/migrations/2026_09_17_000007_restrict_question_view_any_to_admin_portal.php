<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'question.view_any')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId === null) {
            return;
        }

        $instructorRoleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->where('portal', 'instructor')
            ->pluck('id');

        if ($instructorRoleIds->isEmpty()) {
            return;
        }

        DB::table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->whereIn('role_id', $instructorRoleIds)
            ->delete();
    }

    public function down(): void
    {
        // Intentionally irreversible: question.view_any is admin-scope metadata.
    }
};
