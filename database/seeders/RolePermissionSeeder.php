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

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return list<string>
     */
    private function permissionsFor(RoleEnum $role): array
    {
        return match ($role) {
            RoleEnum::SuperAdmin, RoleEnum::Admin => PermissionEnum::values(),

            RoleEnum::ContentEditor => [
                PermissionEnum::QuestionView->value,
                PermissionEnum::QuestionCreate->value,
                PermissionEnum::QuestionUpdate->value,
                PermissionEnum::QuestionDelete->value,
                PermissionEnum::LibraryView->value,
                PermissionEnum::LibraryEdit->value,
                PermissionEnum::ExamManage->value,
            ],

            RoleEnum::Student => [
                PermissionEnum::QuestionView->value,
                PermissionEnum::SessionStart->value,
                PermissionEnum::SessionSubmit->value,
                PermissionEnum::SessionReview->value,
                PermissionEnum::LibraryView->value,
                PermissionEnum::ExamTake->value,
            ],
        };
    }
}
