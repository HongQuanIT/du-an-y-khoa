<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Support\Facades\Route;

/**
 * Admin sidebar items filtered by permission (and optional route readiness).
 *
 * @phpstan-type MenuItem array{label: string, icon: string, route: ?string, permission: ?string, match: ?string}
 */
final class AdminMenu
{
    /**
     * @return list<MenuItem>
     */
    public static function for(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $items = [
            [
                'label' => 'Tổng quan',
                'icon' => 'dashboard',
                'route' => 'admin.dashboard',
                'permission' => null,
                'match' => 'admin.dashboard',
            ],
            [
                'label' => 'Người dùng',
                'icon' => 'group',
                'route' => 'admin.users.index',
                'permission' => Permission::UserView->value,
                'match' => 'admin.users.*',
            ],
            [
                'label' => 'Câu hỏi',
                'icon' => 'quiz',
                'route' => 'admin.questions.index',
                'permission' => Permission::QuestionView->value,
                'match' => 'admin.questions.*',
            ],
            [
                'label' => 'Kỳ thi',
                'icon' => 'assignment',
                'route' => 'admin.exams.index',
                'permission' => Permission::QuestionView->value,
                'match' => 'admin.exams.*',
            ],
            [
                'label' => 'Lớp học',
                'icon' => 'school',
                'route' => 'admin.classrooms.index',
                'permission' => Permission::ClassroomOversee->value,
                'match' => 'admin.classrooms.*',
            ],
            [
                'label' => 'Thư viện',
                'icon' => 'library_books',
                'route' => 'admin.library.index',
                'permission' => Permission::LibraryView->value,
                'match' => 'admin.library.*',
            ],
            [
                'label' => 'CMS',
                'icon' => 'article',
                'route' => 'admin.cms.pages.index',
                'permission' => Permission::CmsManage->value,
                'match' => 'admin.cms.*',
            ],
            [
                'label' => 'Media',
                'icon' => 'perm_media',
                'route' => 'admin.media.index',
                'permission' => Permission::MediaView->value,
                'match' => 'admin.media.*',
            ],
            [
                'label' => 'Báo cáo',
                'icon' => 'analytics',
                'route' => 'admin.reports.index',
                'permission' => Permission::ReportExport->value,
                'match' => 'admin.reports.*',
            ],
            [
                'label' => 'Bảng giá',
                'icon' => 'payments',
                'route' => 'admin.billing.plans.index',
                'permission' => Permission::BillingManage->value,
                'match' => 'admin.billing.*',
            ],
            [
                'label' => 'Phân quyền',
                'icon' => 'admin_panel_settings',
                'route' => 'admin.roles.index',
                'permission' => Permission::RoleManage->value,
                'match' => 'admin.roles.*',
            ],
            [
                'label' => 'Cài đặt',
                'icon' => 'settings',
                'route' => 'admin.settings.index',
                'permission' => Permission::SystemManage->value,
                'match' => 'admin.settings.*',
            ],
            [
                'label' => 'Audit',
                'icon' => 'history',
                'route' => 'admin.audit.index',
                'permission' => Permission::AuditView->value,
                'match' => 'admin.audit.*',
            ],
        ];

        $visible = [];

        foreach ($items as $item) {
            if ($item['permission'] !== null && ! $user->can($item['permission'])) {
                continue;
            }

            $routeReady = $item['route'] !== null && Route::has($item['route']);

            $visible[] = [
                'label' => $item['label'],
                'icon' => $item['icon'],
                'route' => $routeReady ? $item['route'] : null,
                'permission' => $item['permission'],
                'match' => $item['match'],
                'coming_soon' => ! $routeReady && $item['route'] !== 'admin.dashboard',
            ];
        }

        return $visible;
    }
}
