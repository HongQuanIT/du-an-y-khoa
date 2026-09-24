<?php

declare(strict_types=1);

use App\Support\Enums\Role as RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION_NAME = 'editor_notification.view';

    public function up(): void
    {
        $now = now();

        DB::table('permissions')->insertOrIgnore([
            'name' => self::PERMISSION_NAME,
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permissionId = DB::table('permissions')
            ->where('name', self::PERMISSION_NAME)
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId !== null) {
            $roleId = DB::table('roles')
                ->where('name', RoleEnum::ContentEditor->value)
                ->where('guard_name', 'web')
                ->value('id');

            if ($roleId !== null) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', self::PERMISSION_NAME)
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId !== null) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
