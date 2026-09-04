<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Enums\Permission as PermissionEnum;
use App\Support\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Provisions the RBAC baseline from the central enums.
 * See srs/00-nen-tang/03-phan-quyen-rbac.md.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->flushPermissionCache();

        DB::transaction(function (): void {
            foreach (PermissionEnum::values() as $permission) {
                Permission::findOrCreate($permission, 'web');
            }

            foreach (RoleEnum::cases() as $role) {
                $roleModel = Role::findOrCreate($role->value, 'web');
                $roleModel->forceFill(['portal' => $role->portal()->value])->save();
                $roleModel->syncPermissions($this->permissionsFor($role));
            }
        });

        // Flush again after sync so PHP-FPM / Redis never keep a stale map.
        $this->flushPermissionCache();
    }

    private function flushPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        try {
            Artisan::call('permission:cache-reset');
        } catch (\Throwable) {
            // Command may be unavailable in some test boots; forgetCached is enough.
        }

        // Belt-and-suspenders: clear the configured cache key on the default store
        // (Redis in Docker) in case registrar flush and artisan diverge.
        try {
            $key = (string) config('permission.cache.key', 'spatie.permission.cache');
            \Illuminate\Support\Facades\Cache::forget($key);
            $store = config('permission.cache.store');
            if (is_string($store) && $store !== '' && $store !== 'default') {
                \Illuminate\Support\Facades\Cache::store($store)->forget($key);
            }
        } catch (\Throwable) {
            // Ignore cache-store failures during early boot / tests.
        }
    }

    /**
     * @return list<string>
     */
    private function permissionsFor(RoleEnum $role): array
    {
        return match ($role) {
            // Super Admin: toàn quyền trừ soạn/sửa nội dung câu hỏi (tránh xung đột với content_editor).
            RoleEnum::SuperAdmin => array_values(array_filter(
                PermissionEnum::values(),
                static fn (string $permission): bool => ! in_array($permission, [
                    PermissionEnum::QuestionCreate->value,
                    PermissionEnum::QuestionUpdate->value,
                ], true),
            )),

            // Admin: oversight + publish/private/retire/xoá QBank, không soạn/sửa nội dung.
            RoleEnum::Admin => array_values(array_filter(
                PermissionEnum::values(),
                static fn (string $permission): bool => ! in_array($permission, [
                    PermissionEnum::QuestionCreate->value,
                    PermissionEnum::QuestionUpdate->value,
                ], true),
            )),

            RoleEnum::ContentEditor => [
                PermissionEnum::CmsManage->value,
                PermissionEnum::MediaView->value,
                PermissionEnum::MediaManage->value,
                PermissionEnum::QuestionView->value,
                PermissionEnum::QuestionCreate->value,
                PermissionEnum::QuestionUpdate->value,
                PermissionEnum::QuestionDelete->value,
                PermissionEnum::TopicView->value,
                PermissionEnum::TopicCreate->value,
                PermissionEnum::TopicUpdate->value,
                PermissionEnum::TopicDelete->value,
                PermissionEnum::LibraryView->value,
                PermissionEnum::LibraryEdit->value,
                PermissionEnum::ExamManage->value,
            ],

            RoleEnum::Instructor => [
                PermissionEnum::QuestionView->value,
                PermissionEnum::QuestionReview->value,
                PermissionEnum::LibraryView->value,
                PermissionEnum::ClassroomCreate->value,
                PermissionEnum::ClassroomManage->value,
                PermissionEnum::ClassroomJoin->value,
                PermissionEnum::ClassroomModerate->value,
                PermissionEnum::LiveStart->value,
                PermissionEnum::LiveJoin->value,
                PermissionEnum::ExamTake->value,
            ],

            RoleEnum::Partner => [
                PermissionEnum::PartnerPortal->value,
                PermissionEnum::PartnerCodesManage->value,
                PermissionEnum::PartnerReferralsView->value,
                PermissionEnum::PartnerCommissionsView->value,
            ],

            RoleEnum::Student => [
                PermissionEnum::QuestionView->value,
                PermissionEnum::SessionStart->value,
                PermissionEnum::SessionSubmit->value,
                PermissionEnum::SessionReview->value,
                PermissionEnum::LibraryView->value,
                PermissionEnum::ExamTake->value,
                PermissionEnum::ClassroomJoin->value,
                PermissionEnum::LiveJoin->value,
            ],
        };
    }
}
