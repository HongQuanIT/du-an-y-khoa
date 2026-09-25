<?php

declare(strict_types=1);

use App\Support\Enums\Role as RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSION_NAMES = [
        'reviewer_dashboard.view',
        'reviewer_notification.view',
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::PERMISSION_NAMES as $permissionName) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $permissionName,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roleId = DB::table('roles')
            ->where('name', RoleEnum::Reviewer->value)
            ->where('guard_name', 'web')
            ->value('id');

        if ($roleId !== null) {
            $permissionIds = DB::table('permissions')
                ->whereIn('name', self::PERMISSION_NAMES)
                ->where('guard_name', 'web')
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
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
        $permissionIds = DB::table('permissions')
            ->whereIn('name', self::PERMISSION_NAMES)
            ->where('guard_name', 'web')
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
