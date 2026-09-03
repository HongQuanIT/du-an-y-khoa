<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Support\Facades\Route;
use Modules\Admin\Models\ContactInquiry;
use Modules\QuestionBank\Enums\QuestionReviewStatus;
use Modules\QuestionBank\Models\QuestionReviewRequest;

/**
 * Admin sidebar items filtered by permission (and optional route readiness).
 *
 * @phpstan-type MenuItem array{
 *     label: string,
 *     icon: string,
 *     route: ?string,
 *     url?: ?string,
 *     permission: ?string,
 *     match: null|string|list<string>,
 *     path?: string,
 *     external?: bool,
 *     badge?: int
 * }
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
                'label' => 'Feedback câu hỏi',
                'icon' => 'rate_review',
                'route' => 'admin.question-feedback.index',
                'permission' => Permission::QuestionView->value,
                'match' => 'admin.question-feedback.*',
            ],
            [
                'label' => 'Phân loại',
                'icon' => 'category',
                'route' => 'admin.taxonomy.index',
                'permission' => Permission::TopicView->value,
                'match' => [
                    'admin.taxonomy.*',
                    'admin.blueprints.*',
                    'admin.medical-taxonomy.*',
                    'admin.tags.*',
                ],
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
                'label' => 'Liên hệ',
                'icon' => 'mail',
                'route' => 'admin.contacts.index',
                'permission' => Permission::ContactView->value,
                'match' => 'admin.contacts.*',
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
                'label' => 'Gói & bảng giá',
                'icon' => 'sell',
                'route' => 'admin.billing.plans.index',
                'permission' => Permission::BillingManage->value,
                'match' => ['admin.billing.plans.*', 'admin.billing.plan-prices.*'],
            ],
            [
                'label' => 'Lịch sử Premium',
                'icon' => 'workspace_premium',
                'route' => 'admin.billing.subscriptions.index',
                'permission' => Permission::BillingManage->value,
                'match' => 'admin.billing.subscriptions.*',
            ],
            [
                'label' => 'Thanh toán',
                'icon' => 'payments',
                'route' => 'admin.billing.payments.index',
                'permission' => Permission::BillingManage->value,
                'match' => 'admin.billing.payments.*',
            ],
            [
                'label' => 'Cổng thanh toán',
                'icon' => 'account_balance',
                'route' => 'admin.billing.gateways.index',
                'permission' => Permission::BillingManage->value,
                'match' => 'admin.billing.gateways.*',
            ],
            [
                'label' => 'Cộng tác viên',
                'icon' => 'handshake',
                'route' => 'admin.partners.index',
                'permission' => Permission::AdminPartnersManage->value,
                'match' => [
                    'admin.partners.index',
                    'admin.partners.show',
                    'admin.partners.update',
                ],
            ],
            [
                'label' => 'Chi trả CTV',
                'icon' => 'account_balance_wallet',
                'route' => 'admin.partners.payouts.index',
                'permission' => Permission::AdminPartnersPayouts->value,
                'match' => 'admin.partners.payouts.*',
            ],
            [
                'label' => 'Phân quyền',
                'icon' => 'admin_panel_settings',
                'route' => 'admin.roles.index',
                'permission' => Permission::RoleManage->value,
                'match' => 'admin.roles.*',
            ],
            [
                'label' => 'Thông báo',
                'icon' => 'notifications',
                'route' => 'admin.notifications.index',
                'permission' => null,
                'match' => 'admin.notifications.*',
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
            [
                'label' => 'Horizon',
                'icon' => 'monitoring',
                'route' => 'horizon.index',
                'url' => '/horizon',
                'permission' => Permission::SystemManage->value,
                'match' => null,
                'path' => 'horizon*',
                'external' => true,
            ],
        ];

        $visible = [];

        foreach ($items as $item) {
            if ($item['permission'] !== null && ! $user->can($item['permission'])) {
                continue;
            }

            $routeReady = $item['route'] !== null && Route::has($item['route']);
            $url = $item['url'] ?? null;
            $hrefReady = $routeReady || is_string($url);

            $visible[] = [
                'label' => $item['label'],
                'icon' => $item['icon'],
                'route' => $routeReady ? $item['route'] : null,
                'url' => $routeReady ? null : $url,
                'permission' => $item['permission'],
                'match' => $item['match'],
                'path' => $item['path'] ?? null,
                'external' => (bool) ($item['external'] ?? false),
                'coming_soon' => ! $hrefReady && $item['route'] !== 'admin.dashboard',
                'badge' => match (true) {
                    $item['route'] === 'admin.questions.index' && QuestionAccess::isReviewer($user) => QuestionReviewRequest::query()
                        ->where('status', QuestionReviewStatus::Pending->value)
                        ->count(),
                    $item['route'] === 'admin.contacts.index' => ContactInquiry::newCount(),
                    default => 0,
                },
            ];
        }

        return $visible;
    }
}
