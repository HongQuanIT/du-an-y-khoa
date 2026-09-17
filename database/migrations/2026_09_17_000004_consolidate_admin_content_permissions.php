<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $newPermissions = [
            'classroom_oversight.update',
            'cms.view',
            'cms.create',
            'cms.update',
            'cms.delete',
            'report_schedule.schedule',
        ];

        foreach ($newPermissions as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->copyRolePermissions([
            'cms.view' => ['cms_page.view_any', 'cms_page.view', 'cms_menu.view', 'cms_banner.view', 'cms_faq.view'],
            'cms.create' => ['cms_banner.create', 'cms_faq.create'],
            'cms.update' => ['cms_page.update', 'cms_menu.update', 'cms_banner.update', 'cms_faq.update', 'cms_faq.reorder'],
            'cms.delete' => ['cms_banner.delete', 'cms_faq.delete'],
            'media.upload' => ['media.import'],
            'report_schedule.schedule' => ['report_schedule.create'],
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @param array<string, list<string>> $mapping */
    private function copyRolePermissions(array $mapping): void
    {
        foreach ($mapping as $targetName => $sourceNames) {
            $targetId = DB::table('permissions')->where('name', $targetName)->where('guard_name', 'web')->value('id');
            if ($targetId === null) {
                continue;
            }

            $roleIds = DB::table('role_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('permissions.guard_name', 'web')
                ->whereIn('permissions.name', $sourceNames)
                ->pluck('role_has_permissions.role_id')
                ->unique();

            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $targetId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void {}
};
