<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Enums\Permission as PermissionEnum;
use App\Support\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
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
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function (): void {
            foreach (PermissionEnum::values() as $permission) {
                Permission::findOrCreate($permission, 'web');
            }

            foreach (RoleEnum::cases() as $role) {
                Role::findOrCreate($role->value, 'web')
                    ->syncPermissions($this->permissionsFor($role));
            }
        });

        // Ensure PHP-FPM / Redis do not keep a stale permission map after seed.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        try {
            \Illuminate\Support\Facades\Artisan::call('permission:cache-reset');
        } catch (\Throwable) {
            // Command may be unavailable in some test boots; forgetCached is enough.
        }
    }

    /**
     * @return list<string>
     */
    private function permissionsFor(RoleEnum $role): array
    {
        return match ($role) {
            RoleEnum::SuperAdmin, RoleEnum::Admin => PermissionEnum::values(),

            RoleEnum::ContentEditor => [
                PermissionEnum::CmsManage->value,
                PermissionEnum::QuestionView->value,
                PermissionEnum::QuestionCreate->value,
                PermissionEnum::QuestionUpdate->value,
                PermissionEnum::QuestionDelete->value,
                PermissionEnum::LibraryView->value,
                PermissionEnum::LibraryEdit->value,
                PermissionEnum::ExamManage->value,
            ],

            RoleEnum::Instructor => [
                PermissionEnum::QuestionView->value,
                PermissionEnum::LibraryView->value,
                PermissionEnum::ClassroomCreate->value,
                PermissionEnum::ClassroomManage->value,
                PermissionEnum::ClassroomJoin->value,
                PermissionEnum::ClassroomModerate->value,
                PermissionEnum::LiveStart->value,
                PermissionEnum::LiveJoin->value,
                PermissionEnum::ExamTake->value,
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
