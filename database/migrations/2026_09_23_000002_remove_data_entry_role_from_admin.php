<?php

declare(strict_types=1);

use App\Support\Enums\PortalGroup;
use App\Support\Rbac\PermissionRegistry;
use App\Support\Rbac\PermissionSynchronizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const OLD_ROLE = 'nguoi_nhap_lieu';

    private const EDITOR_ROLE = 'content_editor';

    public function up(): void
    {
        app(PermissionSynchronizer::class)->sync();

        DB::transaction(function (): void {
            $editorRoleId = $this->ensureEditorRole();
            $this->syncEditorPermissions($editorRoleId);
            $oldRole = DB::table('roles')
                ->where('guard_name', 'web')
                ->where('name', self::OLD_ROLE)
                ->first(['id']);

            if ($oldRole !== null) {
                $assignments = DB::table('model_has_roles')
                    ->where('role_id', $oldRole->id)
                    ->get(['model_type', 'model_id']);

                foreach ($assignments as $assignment) {
                    DB::table('model_has_roles')->insertOrIgnore([
                        'role_id' => $editorRoleId,
                        'model_type' => $assignment->model_type,
                        'model_id' => $assignment->model_id,
                    ]);
                }

                DB::table('model_has_roles')->where('role_id', $oldRole->id)->delete();
                DB::table('role_has_permissions')->where('role_id', $oldRole->id)->delete();
                DB::table('roles')->where('id', $oldRole->id)->delete();
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('roles')->insertOrIgnore([
            'name' => self::OLD_ROLE,
            'guard_name' => 'web',
            'display_name' => 'Người nhập liệu',
            'portal' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureEditorRole(): int
    {
        $role = DB::table('roles')
            ->where('guard_name', 'web')
            ->where('name', self::EDITOR_ROLE)
            ->first(['id']);

        if ($role !== null) {
            DB::table('roles')->where('id', $role->id)->update([
                'display_name' => 'Biên tập viên nội dung',
                'portal' => 'editor',
                'updated_at' => now(),
            ]);

            return (int) $role->id;
        }

        return (int) DB::table('roles')->insertGetId([
            'name' => self::EDITOR_ROLE,
            'guard_name' => 'web',
            'display_name' => 'Biên tập viên nội dung',
            'portal' => 'editor',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function syncEditorPermissions(int $roleId): void
    {
        $permissionNames = collect(app(PermissionRegistry::class)->all())
            ->filter(fn ($definition): bool => in_array(PortalGroup::Editor, $definition->portals, true))
            ->keys()
            ->all();

        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $permissionNames)
            ->pluck('id');

        DB::table('role_has_permissions')->where('role_id', $roleId)->delete();

        foreach ($permissionIds as $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }
};
