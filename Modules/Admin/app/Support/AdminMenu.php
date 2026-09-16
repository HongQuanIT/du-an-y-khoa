<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use App\Models\User;
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
 *     permission: string|list<string>|null,
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
                'permission' => 'user.view_any',
                'match' => 'admin.users.*',
            ],
            [
                'label' => 'Dữ liệu học viên',
                'icon' => 'clinical_notes',
                'route' => 'admin.institutions.index',
                'permission' => 'learner_catalog.view_any',
                'match' => [
                    'admin.learner-data.*',
                    'admin.countries.*',
                    'admin.administrative-units.*',
                    'admin.institutions.*',
                    'admin.professions.*',
                    'admin.education-stages.*',
                ],
            ],
            [
                'label' => 'Câu hỏi',
                'icon' => 'quiz',
                'route' => 'admin.questions.index',
                'permission' => 'question.view_any',
                'match' => 'admin.questions.*',
            ],
            [
                'label' => 'Phản hồi câu hỏi',
                'icon' => 'rate_review',
                'route' => 'admin.question-feedback.index',
                'permission' => 'question_feedback.view_any',
                'match' => 'admin.question-feedback.*',
            ],
            [
                'label' => 'Phân loại',
                'icon' => 'category',
                'route' => 'admin.taxonomy.index',
                'permission' => 'taxonomy.view',
                'match' => [
                    'admin.taxonomy.*',
                    'admin.blueprints.*',
                    'admin.curriculum.*',
                    'admin.tags.*',
                ],
            ],
            [
                'label' => 'Kỳ thi',
                'icon' => 'assignment',
                'route' => 'admin.exams.index',
                'permission' => 'exam.view_any',
                'match' => 'admin.exams.*',
            ],
            [
                'label' => 'Lớp học',
                'icon' => 'school',
                'route' => 'admin.classrooms.index',
                'permission' => 'classroom_oversight.view_any',
                'match' => 'admin.classrooms.*',
            ],
            [
                'label' => 'CMS',
                'icon' => 'article',
                'route' => 'admin.cms.pages.index',
                'permission' => 'cms_page.view_any',
                'match' => 'admin.cms.*',
            ],
            [
                'label' => 'Liên hệ',
                'icon' => 'mail',
                'route' => 'admin.contacts.index',
                'permission' => 'contact.view_any',
                'match' => 'admin.contacts.*',
            ],
            [
                'label' => 'Media',
                'icon' => 'perm_media',
                'route' => 'admin.media.index',
                'permission' => 'media.view',
                'match' => 'admin.media.*',
            ],
            [
                'label' => 'Báo cáo',
                'icon' => 'analytics',
                'route' => 'admin.reports.index',
                'permission' => 'report.view',
                'match' => 'admin.reports.*',
            ],
            [
                'label' => 'Gói & bảng giá',
                'icon' => 'sell',
                'route' => 'admin.billing.plans.index',
                'permission' => 'billing_plan.view',
                'match' => ['admin.billing.plans.*', 'admin.billing.plan-prices.*'],
            ],
            [
                'label' => 'Lịch sử Premium',
                'icon' => 'workspace_premium',
                'route' => 'admin.billing.subscriptions.index',
                'permission' => 'billing_subscription.view',
                'match' => 'admin.billing.subscriptions.*',
            ],
            [
                'label' => 'Thanh toán',
                'icon' => 'payments',
                'route' => 'admin.billing.payments.index',
                'permission' => 'billing_payment.view',
                'match' => 'admin.billing.payments.*',
            ],
            [
                'label' => 'Cổng thanh toán',
                'icon' => 'account_balance',
                'route' => 'admin.billing.gateways.index',
                'permission' => 'billing_gateway.view',
                'match' => 'admin.billing.gateways.*',
            ],
            [
                'label' => 'Cộng tác viên',
                'icon' => 'handshake',
                'route' => 'admin.partners.index',
                'permission' => 'partner.view_any',
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
                'permission' => 'partner_payout.view',
                'match' => 'admin.partners.payouts.*',
            ],
            [
                'label' => 'Phân quyền',
                'icon' => 'admin_panel_settings',
                'route' => 'admin.roles.index',
                'permission' => 'role.view_any',
                'match' => 'admin.roles.*',
            ],
            [
                'label' => 'Thông báo',
                'icon' => 'notifications',
                'route' => 'admin.notifications.index',
                'permission' => 'notification_broadcast.view',
                'match' => 'admin.notifications.*',
            ],
            [
                'label' => 'Cài đặt',
                'icon' => 'settings',
                'route' => 'admin.settings.index',
                'permission' => 'system_setting.view',
                'match' => 'admin.settings.*',
            ],
            [
                'label' => 'Audit',
                'icon' => 'history',
                'route' => 'admin.audit.index',
                'permission' => 'audit_log.view',
                'match' => 'admin.audit.*',
            ],
            [
                'label' => 'Horizon',
                'icon' => 'monitoring',
                'route' => 'horizon.index',
                'url' => '/horizon',
                'permission' => 'system_setting.view',
                'match' => null,
                'path' => 'horizon*',
                'external' => true,
            ],
        ];

        $visible = [];

        foreach ($items as $item) {
            if ($item['permission'] !== null && ! self::allows($user, $item['permission'])) {
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

    /** @param  string|list<string>  $permission */
    private static function allows(User $user, string|array $permission): bool
    {
        foreach ((array) $permission as $ability) {
            if ($user->can($ability)) {
                return true;
            }
        }

        return false;
    }
}
