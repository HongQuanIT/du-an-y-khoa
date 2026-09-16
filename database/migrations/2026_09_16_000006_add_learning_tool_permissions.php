<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $names = [
            'learning_tool.flag',
            'learning_tool.highlight',
            'learning_tool.note',
            'learning_tool.research',
        ];

        foreach ($names as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $studentRoleId = DB::table('roles')
            ->where('name', 'student')
            ->where('guard_name', 'web')
            ->value('id');

        if ($studentRoleId === null) {
            return;
        }

        foreach (DB::table('permissions')->whereIn('name', $names)->pluck('id') as $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $studentRoleId,
            ]);
        }
    }

    public function down(): void {}
};
