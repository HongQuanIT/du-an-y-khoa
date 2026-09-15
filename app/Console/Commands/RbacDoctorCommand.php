<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Enums\PortalGroup;
use App\Support\Rbac\PermissionRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

final class RbacDoctorCommand extends Command
{
    protected $signature = 'rbac:doctor';

    protected $description = 'Kiểm tra độ lệch và các invariant bảo mật của RBAC';

    public function handle(PermissionRegistry $registry): int
    {
        foreach (['permissions', 'roles', 'model_has_roles', 'model_has_permissions'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Thiếu bảng [{$table}].");

                return self::FAILURE;
            }
        }

        $guard = (string) config('rbac.guard', 'web');
        $registered = $registry->names();
        $stored = DB::table('permissions')->where('guard_name', $guard)->pluck('name')->all();
        $missing = array_values(array_diff($registered, $stored));
        $unknown = array_values(array_diff($stored, $registered));
        $invalidRolePortals = DB::table('roles')
            ->where('guard_name', $guard)
            ->where(function ($query): void {
                $query->whereNull('portal')->orWhereNotIn('portal', PortalGroup::values());
            })
            ->pluck('name')
            ->all();
        $directAssignments = DB::table('model_has_permissions')->count();
        $multipleRoleQuery = DB::table('model_has_roles')
            ->select('model_type', 'model_id')
            ->groupBy('model_type', 'model_id')
            ->havingRaw('COUNT(*) > 1');
        $multipleRoleUsers = DB::query()->fromSub($multipleRoleQuery, 'multi_role_users')->count();
        $routePermissions = $this->routePermissions();
        $unknownRoutePermissions = array_values(array_diff($routePermissions, $registered));

        $this->table(['Kiểm tra', 'Kết quả'], [
            ['Permission trong catalog', count($registered)],
            ['Permission trong database', count($stored)],
            ['Permission còn thiếu', count($missing)],
            ['Permission ngoài catalog', count($unknown)],
            ['Permission route ngoài catalog', count($unknownRoutePermissions)],
            ['Role sai/thiếu portal', count($invalidRolePortals)],
            ['Permission gán trực tiếp user', $directAssignments],
            ['User có nhiều hơn một role', $multipleRoleUsers],
        ]);

        $this->listIssues('Thiếu permission', $missing, true);
        $this->listIssues('Permission ngoài catalog (không tự động xóa)', $unknown, false);
        $this->listIssues('Permission route chưa khai báo trong catalog', $unknownRoutePermissions, true);
        $this->listIssues('Role sai hoặc thiếu portal', $invalidRolePortals, true);

        if ($directAssignments > 0) {
            $this->error("Có {$directAssignments} permission được gán trực tiếp cho user.");
        }
        if ($multipleRoleUsers > 0) {
            $this->error("Có {$multipleRoleUsers} user đang giữ nhiều hơn một role.");
        }

        $failed = $missing !== []
            || $unknownRoutePermissions !== []
            || $invalidRolePortals !== []
            || $directAssignments > 0
            || $multipleRoleUsers > 0;

        if ($failed) {
            $this->error('RBAC chưa đạt yêu cầu. Có thể chạy rbac:sync để bổ sung permission thiếu.');

            return self::FAILURE;
        }

        $this->info('RBAC hợp lệ.');

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function routePermissions(): array
    {
        $permissions = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware) || ! str_starts_with($middleware, 'permission:')) {
                    continue;
                }

                $arguments = explode(',', substr($middleware, strlen('permission:')), 2);
                foreach (explode('|', $arguments[0]) as $permission) {
                    if ($permission !== '') {
                        $permissions[] = $permission;
                    }
                }
            }
        }

        $permissions = array_values(array_unique($permissions));
        sort($permissions);

        return $permissions;
    }

    /** @param list<mixed> $issues */
    private function listIssues(string $title, array $issues, bool $error): void
    {
        if ($issues === []) {
            return;
        }

        $error ? $this->error($title.':') : $this->warn($title.':');
        foreach ($issues as $issue) {
            $this->line('  - '.(string) $issue);
        }
    }
}
