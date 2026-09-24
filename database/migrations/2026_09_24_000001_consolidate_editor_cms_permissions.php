<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const OLD_PERMISSIONS = [
        'editor_page.view',
        'editor_page.update',
        'editor_faq.view',
        'editor_faq.create',
        'editor_faq.update',
        'editor_faq.delete',
        'editor_banner.view',
        'editor_banner.create',
        'editor_banner.update',
        'editor_banner.delete',
        'editor_landing.view',
        'editor_landing.create',
        'editor_landing.update',
        'editor_landing.delete',
        'editor_menu.view',
        'editor_menu.update',
        'editor_cms.view',
        'editor_cms.create',
        'editor_cms.update',
        'editor_cms.delete',
    ];

    public function up(): void
    {
        $now = now();

        foreach (['cms.view', 'cms.create', 'cms.update', 'cms.delete'] as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Editor CMS mirrors Admin: one cms.* set covering pages/faq/banner/menu/landing.
        $this->copyRolePermissions([
            'cms.view' => [
                'editor_page.view',
                'editor_faq.view',
                'editor_banner.view',
                'editor_landing.view',
                'editor_menu.view',
                'editor_cms.view',
            ],
            'cms.create' => [
                'editor_faq.create',
                'editor_banner.create',
                'editor_landing.create',
                'editor_cms.create',
            ],
            'cms.update' => [
                'editor_page.update',
                'editor_faq.update',
                'editor_banner.update',
                'editor_landing.update',
                'editor_menu.update',
                'editor_cms.update',
            ],
            'cms.delete' => [
                'editor_faq.delete',
                'editor_banner.delete',
                'editor_landing.delete',
                'editor_cms.delete',
            ],
        ]);

        $oldIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', self::OLD_PERMISSIONS)
            ->pluck('id');

        if ($oldIds->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $oldIds)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $oldIds)->delete();
            DB::table('permissions')->whereIn('id', $oldIds)->delete();
        }

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
