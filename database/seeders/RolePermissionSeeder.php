<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Enums\Permission as PermissionEnum;
use App\Support\Enums\Role as RoleEnum;
use App\Support\Rbac\PermissionRegistry;
use App\Support\Rbac\PermissionSynchronizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
            app(PermissionSynchronizer::class)->sync();

            foreach (RoleEnum::cases() as $role) {
                $roleModel = Role::findOrCreate($role->value, 'web');
                $roleModel->forceFill(['portal' => $role->portal()->value])->save();
                // Additive baseline: never remove permissions configured in Admin UI.
                $roleModel->givePermissionTo($this->permissionsFor($role));
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
            Cache::forget($key);
            $store = config('permission.cache.store');
            if (is_string($store) && $store !== '' && $store !== 'default') {
                Cache::store($store)->forget($key);
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
        $registry = app(PermissionRegistry::class);
        $portalPermissions = collect($registry->all())
            ->filter(fn ($definition): bool => in_array($role->portal(), $definition->portals, true))
            ->keys()
            ->values()
            ->all();

        return match ($role) {
            // Super Admin / Admin: oversight + publish; không soạn nội dung / không duyệt lớp 1 / không portal CTV / learner.
            RoleEnum::SuperAdmin,
            RoleEnum::Admin => array_values(array_filter(
                $portalPermissions,
                static fn (string $permission): bool => ! in_array($permission, self::staffDeniedPermissions(), true),
            )),

            RoleEnum::ContentEditor => array_values(array_unique(array_merge([

                PermissionEnum::MediaView->value,

                PermissionEnum::QuestionView->value,
                PermissionEnum::QuestionCreate->value,
                PermissionEnum::QuestionUpdate->value,
                PermissionEnum::QuestionSubmit->value,
                PermissionEnum::QuestionDelete->value,
                'profile.view',
                'profile.update',
                'profile.password_update',
                'profile.avatar_update',
            ], collect($registry->all())
                ->filter(fn ($definition): bool => in_array($definition->module, ['content', 'question_bank'], true))
                ->reject(fn ($definition): bool => str_starts_with($definition->name, 'contact.'))
                ->reject(fn ($definition): bool => in_array($definition->name, [
                    'question.view_any',
                    PermissionEnum::QuestionReview->value,
                    PermissionEnum::QuestionPublish->value,
                    PermissionEnum::QuestionRetire->value,
                    'question.restore',
                    'question_feedback.view_any',
                ], true))
                ->keys()
                ->all()))),

            RoleEnum::Instructor => array_values(array_unique(array_merge($portalPermissions, [
                PermissionEnum::QuestionView->value,
                PermissionEnum::QuestionReview->value,
                PermissionEnum::ClassroomCreate->value,
                PermissionEnum::ClassroomManage->value,
                PermissionEnum::ClassroomJoin->value,
                PermissionEnum::ExamTake->value,
            ]))),

            RoleEnum::Partner => array_values(array_unique(array_merge($portalPermissions, [
                PermissionEnum::PartnerPortal->value,
            ]))),

            RoleEnum::Student => array_values(array_unique(array_merge($portalPermissions, [
                PermissionEnum::QuestionView->value,
                PermissionEnum::SessionCreate->value,
                PermissionEnum::SessionStart->value,
                PermissionEnum::SessionSubmit->value,
                PermissionEnum::SessionReview->value,
                PermissionEnum::ExamTake->value,
                PermissionEnum::ClassroomJoin->value,
            ]))),
        };
    }

    /**
     * Abilities Admin/Super Admin must not inherit from "almost all".
     *
     * @return list<string>
     */
    private static function staffDeniedPermissions(): array
    {
        return [
            // Content editor only — avoid edit conflict with working copy.
            PermissionEnum::QuestionCreate->value,
            PermissionEnum::QuestionUpdate->value,
            PermissionEnum::QuestionSubmit->value,
            'question.clone',
            'question.import',
            // Instructor layer-1 only.
            PermissionEnum::QuestionReview->value,
            // Learner session / feature surface.
            PermissionEnum::SessionStart->value,
            PermissionEnum::SessionSubmit->value,
            PermissionEnum::SessionReview->value,
            PermissionEnum::ExamTake->value,
            // Instructor day-to-day classroom ops (oversight uses oversee / create_on_behalf).
            PermissionEnum::ClassroomCreate->value,
            PermissionEnum::ClassroomManage->value,
            PermissionEnum::ClassroomJoin->value,
            // Partner portal (admin uses admin.partners.*).
            PermissionEnum::PartnerPortal->value,
        ];
    }
}
